<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function company(): array
    {
        config(['ipontaj.billing_enabled' => true, 'cashier.secret' => 'sk_test_fixture', 'ipontaj.stripe_team_price' => 'price_team_49_inclusive']);
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Firma de test']);
        $company->memberships()->create(['user_id' => $user->id, 'role' => 'owner', 'joined_on' => '2026-11-01']);

        return [$user, $company];
    }

    public function test_checkout_and_portal_use_inertia_external_navigation_without_enabling_subscription(): void
    {
        [$user,$company] = $this->company();
        $client = new class implements ClientInterface
        {
            public array $calls = [];

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
            {
                $this->calls[] = ['url' => $absUrl, 'params' => $params];
                $object = str_contains($absUrl, 'checkout/sessions') ? ['id' => 'cs_test_fixture', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/test_fixture'] : (str_contains($absUrl, 'billing_portal/sessions') ? ['id' => 'bps_fixture', 'object' => 'billing_portal.session', 'url' => 'https://billing.stripe.com/p/session/test_fixture'] : ['id' => 'cus_fixture', 'object' => 'customer', 'email' => 'owner@example.test']);

                return [json_encode($object), 200, []];
            }
        };
        ApiRequestor::setHttpClient($client);
        try {
            $this->actingAs($user)->withHeaders(['X-Inertia' => 'true'])->post('/billing/checkout', ['terms_accepted' => true, 'legal_version' => config('legal.version')])->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/c/pay/test_fixture');
            $sessions = array_values(array_filter($client->calls, fn ($c) => str_contains($c['url'], 'checkout/sessions')));
            $this->assertSame('subscription', $sessions[0]['params']['mode']);
            $this->assertSame('price_team_49_inclusive', $sessions[0]['params']['line_items'][0]['price']);
            $this->assertSame('required', $sessions[0]['params']['billing_address_collection']);
            $this->assertSame('false', $sessions[0]['params']['automatic_tax']['enabled']);
            $this->assertStringContainsString('neînregistrat', $sessions[0]['params']['custom_text']['submit']['message']);
            $this->assertDatabaseHas('legal_acceptances', ['company_id' => $company->id, 'purpose' => 'checkout']);
            $this->assertFalse($company->fresh()->subscribed('default'));
            $this->post('/billing/portal')->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://billing.stripe.com/p/session/test_fixture');
        } finally {
            ApiRequestor::setHttpClient(CurlClient::instance());
        }
    }

    public function test_signed_subscription_creation_and_duplicate_event_change_only_target_company_once(): void
    {
        [$user,$company] = $this->company();
        $company->update(['stripe_id' => 'cus_fixture']);
        $other = Company::create(['name' => 'Cealaltă firmă']);
        config(['cashier.webhook.secret' => 'whsec_fixture']);
        $body = json_encode(['id' => 'evt_subscription_fixture', 'type' => 'customer.subscription.created', 'data' => ['object' => ['id' => 'sub_fixture', 'customer' => 'cus_fixture', 'status' => 'active', 'metadata' => ['type' => 'default'], 'items' => ['data' => [['id' => 'si_fixture', 'price' => ['id' => 'price_team_49_inclusive', 'product' => 'prod_fixture'], 'quantity' => 1]]]]]]);
        $time = time();
        $signature = hash_hmac('sha256', $time.'.'.$body, 'whsec_fixture');
        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t=$time,v1=$signature"], $body)->assertOk();
        }
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('subscription_items', 1);
        $this->assertSame(15, $company->fresh()->seatLimit());
        $this->assertSame(5, $other->seatLimit());
    }

    public function test_live_keys_are_rejected_until_commercial_configuration_is_implemented(): void
    {
        [$user] = $this->company();
        config(['cashier.secret' => 'sk_live_fixture']);
        $this->actingAs($user)->post('/billing/checkout')->assertStatus(503);
    }

    public function test_billing_page_exposes_demonstration_mode_without_exposing_secret_or_offering_blocked_live_checkout(): void
    {
        [$user] = $this->company();
        $this->withoutVite();
        $this->actingAs($user)->get('/billing')->assertOk()->assertInertia(fn ($page) => $page->where('billingTestMode', true)->where('billingReady', true));
        config(['cashier.secret' => 'sk_live_fixture']);
        $this->get('/billing')->assertOk()->assertInertia(fn ($page) => $page->where('billingTestMode', false)->where('billingReady', false));
        config(['ipontaj.billing_enabled' => false]);
        $this->get('/billing')->assertOk()->assertInertia(fn ($page) => $page->where('billingEnabled', false)->where('billingReady', false));
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
