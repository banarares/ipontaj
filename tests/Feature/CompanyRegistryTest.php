<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyIdentity;
use App\Services\CompanyRegistry;
use App\Services\ExcelService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 22:30:00', 'UTC'));
        Http::preventStrayRequests();
    }

    private function registryResponse(string $cif = '1234', string $name = 'Atelier Fictiv SRL'): array
    {
        return ['found' => [['date_generale' => ['cui' => (int) $cif, 'data' => now('Europe/Bucharest')->toDateString(), 'denumire' => $name]]], 'notFound' => []];
    }

    private function companyForm(array $overrides = []): array
    {
        return [...['dpa_accepted' => true, 'legal_version' => config('legal.version'), 'cif' => 'RO 1234', 'name' => 'Atelier Fictiv SRL', 'timezone' => 'Europe/Bucharest', 'start_time' => '09:00', 'end_time' => '17:00', 'include_owner' => true, 'schedule_confirmed' => true], ...$overrides];
    }

    private function ownedCompany(array $attributes = []): Company
    {
        $owner = User::factory()->create();
        $company = Company::create([...['name' => 'Atelier Fictiv SRL', 'timezone' => 'Europe/Bucharest'], ...$attributes]);
        $company->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'joined_on' => '2026-01-01']);
        $this->actingAs($owner)->withSession(['company_id' => $company->id]);

        return $company;
    }

    public function test_lookup_normalizes_cif_uses_bucharest_date_and_caches_official_name(): void
    {
        Http::fake([config('services.anaf.endpoint') => Http::response($this->registryResponse())]);
        $this->actingAs(User::factory()->create())->getJson('/company-lookup?cif='.urlencode(' ro 1234 '))
            ->assertOk()->assertJsonPath('status', 'found')->assertJsonPath('cif', '1234')->assertJsonPath('name', 'Atelier Fictiv SRL');
        Http::assertSent(fn (Request $request) => $request->url() === config('services.anaf.endpoint') && $request->method() === 'POST' && $request->data() === [['cui' => 1234, 'data' => '2026-11-25']]);
        $this->getJson('/company-lookup?cif=1234')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_lookup_requires_an_account_and_is_disabled_for_demo(): void
    {
        $this->getJson('/company-lookup?cif=1234')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_demo' => true]))->getJson('/company-lookup?cif=1234')->assertForbidden();
        Http::assertNothingSent();
    }

    public static function invalidCifs(): array
    {
        return [[''], ['1'], ['01234'], ['12345678901'], ['1960101123456'], ['1234; DROP TABLE'], ['https://example.test'], ['RO'], ['12.34']];
    }

    #[DataProvider('invalidCifs')]
    public function test_invalid_cif_is_rejected_before_contacting_anaf(string $cif): void
    {
        $this->actingAs(User::factory()->create())->getJson('/company-lookup?cif='.urlencode($cif))->assertUnprocessable()->assertJsonValidationErrors('cif');
        Http::assertNothingSent();
    }

    public function test_missing_company_is_cached_without_inventing_a_name(): void
    {
        Http::fake(['*' => Http::response(['found' => [], 'notFound' => [1234]])]);
        $this->actingAs(User::factory()->create())->getJson('/company-lookup?cif=1234')->assertNotFound()->assertJsonPath('status', 'not_found')->assertJsonMissingPath('name');
        $this->getJson('/company-lookup?cif=1234')->assertNotFound();
        Http::assertSentCount(1);
        $this->travel(6)->minutes();
        $this->getJson('/company-lookup?cif=1234')->assertNotFound();
        Http::assertSentCount(2);
    }

    public function test_timeout_is_recoverable_and_is_not_cached(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->actingAs(User::factory()->create())->getJson('/company-lookup?cif=1234')->assertStatus(503)->assertJsonPath('status', 'unavailable');
        $this->assertNull(app(CompanyRegistry::class)->cached('1234'));
        $this->travel(2)->seconds();
        $this->getJson('/company-lookup?cif=1234')->assertStatus(503);
        Http::assertSentCount(2);
    }

    public static function unusableResponses(): array
    {
        return [
            [['found' => [], 'notFound' => []], 200],
            [['found' => [], 'notFound' => [9999]], 200],
            [['found' => [['date_generale' => ['cui' => 9999, 'denumire' => 'Altă firmă fictivă', 'data' => '2026-11-25']]], 'notFound' => []], 200],
            [['found' => [['date_generale' => ['cui' => 1234, 'denumire' => 'Atelier Fictiv SRL', 'data' => '2026-11-24']]], 'notFound' => []], 200],
            [['found' => [['date_generale' => ['cui' => 1234, 'denumire' => '', 'data' => '2026-11-25']]], 'notFound' => []], 200],
            ['<html>maintenance</html>', 200],
            [[], 503],
        ];
    }

    #[DataProvider('unusableResponses')]
    public function test_unusable_response_is_not_misrepresented_as_found_or_not_found(array|string $payload, int $status): void
    {
        Http::fake(['*' => Http::response($payload, $status)]);
        $this->actingAs(User::factory()->create())->getJson('/company-lookup?cif=1234')->assertStatus(503)->assertJsonPath('status', 'unavailable');
        $this->assertNull(app(CompanyRegistry::class)->cached('1234'));
    }

    public function test_global_request_limit_and_lock_protect_the_public_api(): void
    {
        Http::fake(['*' => Http::response($this->registryResponse())]);
        $this->actingAs(User::factory()->create())->getJson('/company-lookup?cif=1234')->assertOk();
        $this->getJson('/company-lookup?cif=5678')->assertStatus(429)->assertHeader('Retry-After', '1');
        $this->getJson('/company-lookup?cif=1234')->assertOk();
        Http::assertSentCount(1);
        $this->travel(2)->seconds();
        $lock = Cache::lock('anaf:request:lock', 15);
        $this->assertTrue($lock->get());
        try {
            $this->getJson('/company-lookup?cif=5678')->assertStatus(429);
            Http::assertSentCount(1);
        } finally {
            $lock->release();
        }
    }

    public function test_new_company_requires_cif_but_can_be_created_manually_without_anaf(): void
    {
        $this->actingAs(User::factory()->create())->post('/companies', $this->companyForm(['cif' => '']))->assertSessionHasErrors('cif');
        $this->post('/companies', $this->companyForm(['registry_verified_at' => now()->toIso8601String()]))->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('companies', ['name' => 'Atelier Fictiv SRL', 'cif' => '1234', 'registry_verified_at' => null]);
        Http::assertNothingSent();
    }

    public function test_verified_identity_comes_from_server_cache_and_is_saved_with_company(): void
    {
        Http::fake(['*' => Http::response($this->registryResponse())]);
        $this->actingAs(User::factory()->create())->getJson('/company-lookup?cif=1234')->assertOk();
        $this->post('/companies', $this->companyForm())->assertRedirect('/dashboard');
        $company = Company::firstOrFail();
        $this->assertSame('1234', $company->cif);
        $this->assertSame('Atelier Fictiv SRL', $company->name);
        $this->assertNotNull($company->registry_verified_at);
        Http::assertSentCount(1);
        $this->get('/settings')->assertInertia(fn ($page) => $page->where('company.cif', '1234')->where('company.name', 'Atelier Fictiv SRL'));
    }

    public function test_editing_name_or_cif_clears_verification_and_only_changes_current_company(): void
    {
        $company = $this->ownedCompany(['cif' => '1234', 'registry_verified_at' => now()]);
        $other = Company::create(['name' => 'Alt Atelier Fictiv SRL', 'cif' => '5678', 'timezone' => 'UTC']);
        $this->post('/company/settings', ['name' => 'Denumire editată', 'cif' => 'RO5678', 'reminders' => true, 'company_id' => $other->id, 'registry_verified_at' => now()->toIso8601String()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'Denumire editată', 'cif' => '5678', 'registry_verified_at' => null]);
        $this->assertSame('Alt Atelier Fictiv SRL', $other->refresh()->name);
        $this->post('/company/settings', ['name' => 'Denumire editată', 'cif' => '', 'reminders' => true])->assertSessionHasErrors('cif');
        Http::assertNothingSent();
    }

    public function test_manual_identity_can_be_verified_later_and_verification_survives_unrelated_edits(): void
    {
        $company = $this->ownedCompany(['cif' => '1234']);
        Http::fake(['*' => Http::response($this->registryResponse())]);
        $this->getJson('/company-lookup?cif=1234')->assertOk();
        $this->post('/company/settings', ['name' => $company->name, 'cif' => '1234', 'reminders' => true])->assertSessionHasNoErrors();
        $verifiedAt = $company->refresh()->registry_verified_at;
        $this->assertNotNull($verifiedAt);
        $this->travel(2)->days();
        $this->post('/company/settings', ['name' => $company->name, 'cif' => '1234', 'reminders' => false])->assertSessionHasNoErrors();
        $this->assertTrue($verifiedAt->equalTo($company->refresh()->registry_verified_at));
        $this->post('/company/settings', ['name' => 'Nume nou fictiv', 'cif' => '1234', 'reminders' => false])->assertSessionHasNoErrors();
        $this->assertNull($company->refresh()->registry_verified_at);
    }

    public function test_legacy_company_can_keep_its_name_and_demo_never_stores_real_fiscal_identity(): void
    {
        $company = $this->ownedCompany();
        $this->post('/company/settings', ['name' => $company->name, 'reminders' => false])->assertSessionHasNoErrors();
        $this->assertNull($company->refresh()->cif);
        $company->update(['is_demo' => true]);
        $attributes = app(CompanyIdentity::class)->attributes('1234', 'Demo fictiv', $company);
        $this->assertSame(['name' => 'Demo fictiv', 'cif' => null, 'registry_verified_at' => null], $attributes);
        Http::assertNothingSent();
    }

    public function test_company_identity_is_available_in_data_and_excel_exports(): void
    {
        $company = $this->ownedCompany(['cif' => '1234']);
        $json = $this->get('/company/data')->assertOk()->streamedContent();
        $this->assertSame('1234', json_decode($json, true, flags: JSON_THROW_ON_ERROR)['company']['cif']);
        $path = app(ExcelService::class)->build($company, '2026-11');
        try {
            $this->assertStringContainsString('CIF 1234', json_encode(IOFactory::load($path)->getActiveSheet()->toArray()));
        } finally {
            unlink($path);
        }
    }

    public function test_saving_company_identity_still_requires_consent_for_accountant_email(): void
    {
        $company = $this->ownedCompany(['cif' => '1234']);
        $settings = ['name' => $company->name, 'cif' => '1234', 'reminders' => false, 'accountant_email' => 'contabil@example.test', 'accountant_confirmed' => false];
        $this->post('/company/settings', $settings)->assertSessionHasErrors('accountant_confirmed');
        $this->post('/company/settings', [...$settings, 'accountant_confirmed' => true])->assertSessionHasNoErrors();
        $this->assertNotNull($company->refresh()->accountant_confirmed_at);
    }
}
