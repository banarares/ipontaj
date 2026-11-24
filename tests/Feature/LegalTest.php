<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LegalAcceptance;
use App\Models\LegalRequest;
use App\Models\User;
use App\Notifications\LegalContractConfirmed;
use App\Notifications\LegalRequestReceived;
use App\Services\LegalAgreements;
use App\Services\LegalDocuments;
use App\Services\LegalReadiness;
use App\Services\SourceArchive;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use ZipArchive;

class LegalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    private function registration(array $overrides = []): array
    {
        return [...['first_name' => 'Ana', 'last_name' => 'Exemplu', 'email' => 'ana@example.test', 'password' => 'Molid!Vapor7Nori$42', 'password_confirmation' => 'Molid!Vapor7Nori$42', 'terms_accepted' => true, 'legal_version' => config('legal.version')], ...$overrides];
    }

    private function company(array $overrides = []): array
    {
        return [...['name' => 'Atelier Fictiv SRL', 'cif' => '1234', 'timezone' => 'Europe/Bucharest', 'start_time' => '09:00', 'end_time' => '17:00', 'include_owner' => true, 'schedule_confirmed' => true, 'dpa_accepted' => true, 'legal_version' => config('legal.version')], ...$overrides];
    }

    private function withdrawal(array $overrides = []): array
    {
        return [...['first_name' => 'Ana Maria', 'last_name' => 'Exemplu', 'email' => 'ANA@example.test', 'contract_reference' => 'Cont ana@example.test, comandă fictivă din 24.11.2026', 'confirmed' => true], ...$overrides];
    }

    public function test_legal_documents_are_public_and_downloadable_without_account(): void
    {
        foreach (app(LegalDocuments::class)->index() as $slug => $metadata) {
            $this->get('/'.$slug)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn ($page) => $page->component('Legal')->where('document.title', $metadata['title'])->where('legal.operator.cif', '33388821')->where('legal.operator.vat_registered', false));
        }
        $this->get('/documente/termeni-si-conditii')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8')->assertSee('33388821')->assertSee('49 lei/lună');
        $this->get('/documente/../../.env')->assertNotFound();
        $this->get('/documente/necunoscut')->assertNotFound();
    }

    public function test_registration_requires_explicit_acceptance_of_the_current_terms_and_stores_server_snapshot(): void
    {
        $this->post('/register', $this->registration(['terms_accepted' => false]))->assertSessionHasErrors('terms_accepted');
        $this->assertDatabaseCount('users', 0);
        $this->post('/register', $this->registration(['legal_version' => 'invented']))->assertSessionHasErrors('legal_version');
        $this->assertDatabaseCount('legal_acceptances', 0);
        $this->post('/register', $this->registration(['documents' => ['forged' => true]]))->assertSessionHasNoErrors();
        $acceptance = LegalAcceptance::firstOrFail();
        $this->assertSame('account', $acceptance->purpose);
        $this->assertSame(config('legal.version'), $acceptance->version);
        $this->assertSame(hash('sha256', app(LegalDocuments::class)->text('termeni-si-conditii')), $acceptance->documents['termeni-si-conditii']['sha256']);
        $this->assertArrayNotHasKey('forged', $acceptance->documents);
        $this->assertFalse($acceptance->facts['operator']['vat_registered']);
        $this->get('/acorduri/'.$acceptance->id)->assertOk()->assertSee('SHA-256')->assertSee('49 lei/lună');
        $this->actingAs(User::factory()->create())->get('/acorduri/'.$acceptance->id)->assertNotFound();
    }

    public function test_public_document_copy_has_no_launch_notes_and_license_links_to_repository_and_release_source(): void
    {
        $documents = app(LegalDocuments::class);
        foreach (array_keys($documents->index()) as $slug) {
            $text = $documents->text($slug);
            $this->assertDoesNotMatchRegularExpression('/pregătire pentru lansare|înainte de lansare|propusă pentru lansare|rămân în verificare|încasările reale rămân dezactivate/ui', $text);
        }
        $license = $this->get('/documente/licenta')->assertOk();
        $license->assertSee('https://github.com/banarares/ipontaj', false)->assertSee('/cod-sursa');
        // Editing public copy does not approve infrastructure or enable real payments.
        $this->assertFalse(config('legal.publication_approved'));
        $this->assertNotEmpty(app(LegalReadiness::class)->blockers());
    }

    public function test_previous_contract_snapshot_stays_downloadable_after_public_document_version_changes(): void
    {
        $currentVersion = config('legal.version');
        $this->assertNotSame('2026-11-24.1', $currentVersion);
        config(['legal.version' => '2026-11-24.1']);
        $user = User::factory()->create();
        $agreements = app(LegalAgreements::class);
        $previous = $agreements->record($user, 'account', ['termeni-si-conditii', 'confidentialitate']);
        $oldText = $previous->documents['termeni-si-conditii']['text'];
        $oldHash = $previous->documents['termeni-si-conditii']['sha256'];
        config(['legal.version' => $currentVersion]);

        $this->assertFalse($agreements->hasAccountAgreement($user));
        $this->assertNotSame($oldText, app(LegalDocuments::class)->text('termeni-si-conditii'));
        $this->actingAs($user)->get('/acorduri/'.$previous->id)->assertOk()->assertSee($oldText, false)->assertSee($oldHash);
        $previous->refresh();
        $this->assertSame(hash('sha256', $oldText), $previous->documents['termeni-si-conditii']['sha256']);
        $this->assertSame($oldText, file_get_contents(resource_path('legal/2026-11-24.1/termeni-si-conditii.md')));
    }

    public function test_business_requires_dpa_and_consumer_can_create_without_cif_or_professional_authority(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create())->post('/companies', $this->company(['dpa_accepted' => false]))->assertSessionHasErrors('dpa_accepted');
        $this->assertDatabaseCount('companies', 0);
        $this->post('/companies', $this->company(['customer_type' => 'consumer', 'cif' => null, 'name' => 'Spațiu personal fictiv', 'dpa_accepted' => false]))->assertSessionHasNoErrors()->assertRedirect('/dashboard');
        $company = Company::firstOrFail();
        $this->assertSame('consumer', $company->customer_type);
        $this->assertNull($company->cif);
        $this->assertNull($company->registry_verified_at);
        $acceptance = LegalAcceptance::where('purpose', 'company')->firstOrFail();
        $this->assertArrayNotHasKey('acord-prelucrare-date', $acceptance->documents);
        Http::assertNothingSent();
    }

    public function test_legacy_owner_can_record_dpa_but_cannot_accept_for_a_foreign_company(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'Atelier Fictiv SRL']);
        $company->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'joined_on' => '2026-11-01']);
        $foreign = Company::create(['name' => 'Altă firmă fictivă']);
        $form = ['terms_accepted' => true, 'dpa_accepted' => true, 'legal_version' => config('legal.version'), 'company_id' => $foreign->id];
        $this->actingAs($owner)->post('/acorduri', $form)->assertNotFound();
        $this->assertDatabaseCount('legal_acceptances', 0);
        $this->post('/acorduri', [...$form, 'company_id' => $company->id])->assertRedirect('/dashboard');
        $this->assertTrue(app(LegalAgreements::class)->hasAccountAgreement($owner));
        $this->assertTrue(app(LegalAgreements::class)->hasCompanyAgreement($company));
        $this->assertFalse(app(LegalAgreements::class)->hasCompanyAgreement($foreign));
        $this->get('/settings/profile')->assertInertia(fn ($page) => $page->has('legalAcceptances', 2));
    }

    public function test_guest_withdrawal_requires_confirmation_and_issues_a_private_durable_receipt(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00:00', 'Europe/Bucharest'));
        $this->post('/retragere', $this->withdrawal(['confirmed' => false]))->assertSessionHasErrors('confirmed');
        $this->assertDatabaseCount('legal_requests', 0);
        $this->from('/retragere')->post('/retragere', $this->withdrawal())->assertRedirect('/retragere')->assertSessionHas('legal_request.receipt_url');
        $record = LegalRequest::firstOrFail();
        $this->assertSame('pending', $record->status);
        $this->assertSame('withdrawal', $record->kind);
        $this->assertSame('ana@example.test', $record->email);
        $this->assertSame('Ana Maria Exemplu', $record->name);
        $this->assertSame('Ana Maria', $record->first_name);
        $this->assertSame('2026-12-08', $record->due_at->setTimezone('Europe/Bucharest')->toDateString());
        Notification::assertSentOnDemandTimes(LegalRequestReceived::class, 2);
        $url = session('legal_request.receipt_url');
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee($record->id)->assertSee('Ana Maria Exemplu');
        $this->get('/confirmare-solicitare/'.$record->id)->assertForbidden();
        $this->travel(8)->days();
        $this->get($url)->assertForbidden();
    }

    public function test_mail_failure_never_discards_a_received_withdrawal(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Furnizor email indisponibil în test'));
        $this->from('/retragere')->post('/retragere', $this->withdrawal())->assertRedirect('/retragere')->assertSessionHas('legal_request.reference');
        $this->assertDatabaseHas('legal_requests', ['email' => 'ana@example.test', 'status' => 'pending']);
    }

    public function test_data_rights_request_requires_no_marketing_or_blanket_privacy_consent_and_uses_calendar_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-31 12:00:00', 'Europe/Bucharest'));
        $this->post('/solicitari-date', [...$this->withdrawal(['contract_reference' => null]), 'request_type' => 'access', 'message' => 'Solicit propriile date din contul fictiv.'])->assertSessionHasNoErrors();
        $record = LegalRequest::firstOrFail();
        $this->assertSame('access', $record->kind);
        $this->assertSame('2027-02-28', $record->due_at->setTimezone('Europe/Bucharest')->toDateString());
        $this->assertNull($record->contract_reference);
    }

    public function test_request_form_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/retragere', $this->withdrawal())->assertRedirect();
        }
        $this->post('/retragere', $this->withdrawal())->assertStatus(429);
        $this->assertDatabaseCount('legal_requests', 5);
    }

    public function test_legal_request_register_does_not_pretend_to_refund_or_send_resolution(): void
    {
        $this->post('/retragere', $this->withdrawal());
        $record = LegalRequest::firstOrFail();
        $this->artisan('pontaj:legal:requests')->assertSuccessful();
        $this->artisan('pontaj:legal:requests', ['--resolve' => $record->id])->assertFailed();
        $this->artisan('pontaj:legal:requests', ['--resolve' => $record->id, '--resolution' => 'Soluție fictivă comunicată separat.'])->assertSuccessful();
        $this->assertSame('resolved', $record->refresh()->status);
        $this->assertNotNull($record->resolved_at);
        Notification::assertSentOnDemandTimes(LegalRequestReceived::class, 2);
    }

    public function test_production_keeps_public_information_accessible_but_refuses_unprepared_launch(): void
    {
        $this->app->instance('env', 'production');
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->get('/termeni-si-conditii')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Content-Security-Policy');
        $this->post('/register', $this->registration())->assertStatus(503);
        $this->assertDatabaseCount('users', 0);
        $this->artisan('pontaj:legal:check')->assertFailed();
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect('/acorduri');
    }

    public function test_source_archive_contains_installable_sources_but_no_runtime_data_or_secrets(): void
    {
        $destination = storage_path('framework/testing/legal-source-fixture.zip');
        config(['legal.source_archive' => $destination]);
        try {
            $this->assertGreaterThan(50, app(SourceArchive::class)->build());
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($destination));
            foreach (['.env', 'database/database.sqlite', 'public/hot', 'bootstrap/cache/config.php', '.runtime/stripe-verification.json', 'storage/logs/laravel.log'] as $path) {
                $this->assertFalse($zip->locateName('ipontaj/'.$path));
            }
            $this->assertNotFalse($zip->getFromName('ipontaj/LICENSE'));
            $this->assertNotFalse($zip->getFromName('ipontaj/composer.lock'));
            $this->assertNotFalse($zip->getFromName('ipontaj/.env.example'));
            $this->assertSame("ignore-scripts=true\n", $zip->getFromName('ipontaj/.npmrc'));
            $this->assertNotFalse($zip->getFromName('ipontaj/public/.htaccess'));
            $this->assertNotFalse($zip->locateName('ipontaj/bootstrap/cache/'));
            $this->assertNotFalse($zip->locateName('ipontaj/storage/framework/views/'));
            $this->assertNotFalse($zip->getFromName('ipontaj/resources/legal/third-party/npm/@fontsource/inter/LICENSE'));
            $manifest = json_decode($zip->getFromName('ipontaj/SOURCE_MANIFEST.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(hash('sha256', $zip->getFromName('ipontaj/app/Services/LegalDocuments.php')), $manifest['files']['app/Services/LegalDocuments.php']);
            $zip->close();
            $this->get('/cod-sursa')->assertDownload('ipontaj-sursa.zip');
        } finally {
            @unlink($destination);
        }
    }

    public function test_paid_checkout_contract_notification_matches_customer_and_session_and_is_deduplicated(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'Spațiu de test', 'stripe_id' => 'cus_legal_fixture']);
        $acceptance = app(LegalAgreements::class)->record($owner, 'checkout', ['termeni-si-conditii', 'abonamente-si-anulare', 'retragere'], $company);
        $acceptance->update(['facts' => [...$acceptance->facts, 'checkout_session_id' => 'cs_legal_fixture', 'test_mode' => true]]);
        config(['cashier.webhook.secret' => 'whsec_legal_fixture']);
        $send = function (string $eventId, string $customer) use ($acceptance): void {
            $body = json_encode(['id' => $eventId, 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_legal_fixture', 'customer' => $customer, 'payment_status' => 'paid', 'metadata' => ['legal_acceptance_id' => (string) $acceptance->id]]]]);
            $time = time();
            $signature = hash_hmac('sha256', $time.'.'.$body, 'whsec_legal_fixture');
            $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t=$time,v1=$signature"], $body)->assertOk();
        };
        $send('evt_legal_wrong', 'cus_foreign_fixture');
        $this->assertNull($acceptance->refresh()->confirmed_at);
        Notification::assertNothingSent();
        $send('evt_legal_correct', 'cus_legal_fixture');
        $send('evt_legal_correct', 'cus_legal_fixture');
        $this->assertNotNull($acceptance->refresh()->confirmed_at);
        Notification::assertSentToTimes($owner, LegalContractConfirmed::class, 1);
        $message = (new LegalContractConfirmed($acceptance->id))->toMail($owner);
        $this->assertCount(1, $message->rawAttachments);
    }
}
