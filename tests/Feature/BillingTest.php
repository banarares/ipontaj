<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LegalAcceptance;
use App\Models\User;
use App\Services\LegalAgreements;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function company(bool $live = false): array
    {
        $mode = $live ? 'live' : 'test';
        config(['ipontaj.billing_enabled' => true, 'cashier.key' => 'pk_'.$mode.'_fixture', 'cashier.secret' => 'sk_'.$mode.'_fixture', 'cashier.webhook.secret' => 'whsec_fixture', 'cashier.currency' => 'ron', 'ipontaj.stripe_team_price' => 'price_team_49_inclusive']);
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Firma de test']);
        $company->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'joined_on' => '2026-11-01']);

        return [$user, $company];
    }

    private function stripe(bool $live = false, array $priceChanges = []): ClientInterface
    {
        $client = new class($live, $priceChanges) implements ClientInterface
        {
            public array $calls = [];

            public function __construct(private bool $live, private array $priceChanges) {}

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
            {
                $this->calls[] = ['url' => $absUrl, 'params' => $params];
                if (str_contains($absUrl, '/prices/')) {
                    $object = array_replace(['id' => 'price_team_49_inclusive', 'object' => 'price', 'active' => true, 'livemode' => $this->live, 'currency' => 'ron', 'unit_amount' => 4900, 'type' => 'recurring', 'recurring' => ['interval' => 'month', 'interval_count' => 1]], $this->priceChanges);
                } elseif (str_contains($absUrl, 'checkout/sessions')) {
                    $object = ['id' => 'cs_fixture', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/fixture'];
                } elseif (str_contains($absUrl, 'billing_portal/sessions')) {
                    $object = ['id' => 'bps_fixture', 'object' => 'billing_portal.session', 'url' => 'https://billing.stripe.com/p/session/fixture'];
                } else {
                    $object = ['id' => 'cus_fixture', 'object' => 'customer', 'email' => 'owner@example.test'];
                }

                return [json_encode($object), 200, []];
            }
        };
        ApiRequestor::setHttpClient($client);

        return $client;
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(CurlClient::instance());
        parent::tearDown();
    }

    public function test_checkout_and_portal_use_external_navigation_without_enabling_subscription(): void
    {
        [$user, $company] = $this->company();
        $client = $this->stripe();
        $this->actingAs($user)->withHeaders(['X-Inertia' => 'true'])->post('/billing/checkout', ['terms_accepted' => true, 'legal_version' => config('legal.version')])->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/c/pay/fixture');
        $sessions = array_values(array_filter($client->calls, fn ($c) => str_contains($c['url'], 'checkout/sessions')));
        $this->assertSame('subscription', $sessions[0]['params']['mode']);
        $this->assertSame('price_team_49_inclusive', $sessions[0]['params']['line_items'][0]['price']);
        $this->assertSame('required', $sessions[0]['params']['billing_address_collection']);
        $this->assertSame('false', $sessions[0]['params']['automatic_tax']['enabled']);
        $this->assertStringContainsString('neînregistrat', $sessions[0]['params']['custom_text']['submit']['message']);
        $this->assertTrue(LegalAcceptance::where('purpose', 'checkout')->firstOrFail()->facts['test_mode']);
        $this->assertFalse($company->fresh()->subscribed('default'));
        $this->post('/billing/portal')->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://billing.stripe.com/p/session/fixture');
    }

    public function test_live_checkout_in_production_records_real_mode_without_falsifying_operator_confirmations(): void
    {
        [$user, $company] = $this->company(true);
        $this->app->instance('env', 'production');
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['app.url' => 'https://ipontaj.example', 'legal.publication_approved' => false]);
        $agreements = app(LegalAgreements::class);
        $agreements->record($user, 'account', ['termeni-si-conditii']);
        $agreements->record($user, 'company', ['acord-prelucrare-date'], $company);
        $this->stripe(true);
        $this->actingAs($user)->withHeaders(['X-Inertia' => 'true'])->post('/billing/checkout', ['terms_accepted' => true, 'legal_version' => config('legal.version')])->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/c/pay/fixture');
        $this->assertFalse(LegalAcceptance::where('purpose', 'checkout')->firstOrFail()->facts['test_mode']);
        $this->assertFalse(config('legal.publication_approved'));
        $this->assertFalse($company->fresh()->subscribed('default'));
    }

    public function test_wrong_price_is_refused_before_creating_customer_contract_or_checkout(): void
    {
        [$user] = $this->company(true);
        $client = $this->stripe(true, ['unit_amount' => 9900]);
        $this->actingAs($user)->post('/billing/checkout', ['terms_accepted' => true, 'legal_version' => config('legal.version')])->assertStatus(503);
        $this->assertCount(1, $client->calls);
        $this->assertDatabaseCount('legal_acceptances', 0);
    }

    public function test_missing_webhook_or_mixed_keys_cannot_open_checkout(): void
    {
        [$user] = $this->company(true);
        $client = $this->stripe(true);
        config(['cashier.key' => 'pk_test_fixture']);
        $this->actingAs($user)->post('/billing/checkout')->assertStatus(503);
        config(['cashier.key' => 'pk_live_fixture', 'cashier.webhook.secret' => '']);
        $this->post('/billing/checkout')->assertStatus(503);
        $this->assertCount(0, $client->calls);
    }

    public function test_billing_page_reports_both_modes_without_exposing_credentials(): void
    {
        [$user] = $this->company();
        $this->withoutVite();
        $this->actingAs($user)->get('/billing')->assertOk()->assertInertia(fn ($page) => $page->where('billingTestMode', true)->where('billingReady', true))->assertDontSee('sk_test_fixture');
        config(['cashier.secret' => 'sk_live_fixture', 'cashier.key' => 'pk_live_fixture']);
        $this->get('/billing')->assertOk()->assertInertia(fn ($page) => $page->where('billingTestMode', false)->where('billingReady', true))->assertDontSee('sk_live_fixture');
        config(['ipontaj.billing_enabled' => false]);
        $this->get('/billing')->assertOk()->assertInertia(fn ($page) => $page->where('billingEnabled', false)->where('billingReady', false));
    }

    public function test_signed_subscription_event_changes_only_target_company_once_and_rejects_wrong_mode(): void
    {
        [$user, $company] = $this->company();
        $company->update(['stripe_id' => 'cus_fixture']);
        $other = Company::create(['name' => 'Cealaltă firmă']);
        $payload = ['id' => 'evt_subscription_fixture', 'livemode' => false, 'type' => 'customer.subscription.created', 'data' => ['object' => ['id' => 'sub_fixture', 'customer' => 'cus_fixture', 'status' => 'active', 'metadata' => ['type' => 'default'], 'items' => ['data' => [['id' => 'si_fixture', 'price' => ['id' => 'price_team_49_inclusive', 'product' => 'prod_fixture'], 'quantity' => 1]]]]]];
        $send = function (array $event, string $signingSecret = 'whsec_fixture') {
            $body = json_encode($event);
            $time = time();
            $signature = hash_hmac('sha256', $time.'.'.$body, $signingSecret);

            return $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t=$time,v1=$signature"], $body);
        };
        $send([...$payload, 'livemode' => true])->assertStatus(400);
        $send($payload, 'whsec_wrong_fixture')->assertForbidden();
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('webhook_events', 0);
        $send($payload)->assertOk();
        $send($payload)->assertOk();
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('subscription_items', 1);
        $this->assertDatabaseCount('user_notifications', 1);
        $this->assertSame(15, $company->fresh()->seatLimit());
        $this->assertSame(5, $other->seatLimit());
    }

    public function test_consumer_checkout_requires_explicit_early_start_request_without_waiving_withdrawal(): void
    {
        [$user, $company] = $this->company();
        $company->update(['customer_type' => 'consumer']);
        $this->actingAs($user)->post('/billing/checkout', ['terms_accepted' => true, 'legal_version' => config('legal.version'), 'service_start_requested' => false])->assertSessionHasErrors('service_start_requested');
        $this->post('/billing/checkout', ['terms_accepted' => false, 'legal_version' => config('legal.version'), 'service_start_requested' => true])->assertSessionHasErrors('terms_accepted');
        $this->assertDatabaseCount('legal_acceptances', 0);
    }
}
