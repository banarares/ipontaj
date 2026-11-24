<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Http::preventStrayRequests();
        config(['cache.default' => 'array']);
        config(['inertia.ssr.enabled' => false]);
    }

    private function contact(array $overrides = []): array
    {
        return [...['first_name' => 'Ana', 'last_name' => 'Fictiv', 'email' => 'ana@example.test', 'subject' => 'Asistență test', 'message' => 'Acesta este un mesaj fictiv pentru verificarea formularului.', 'website' => ''], ...$overrides];
    }

    private function enableTurnstile(): void
    {
        config(['cloudflare.turnstile.enabled' => true, 'cloudflare.turnstile.site_key' => 'site-fixture', 'cloudflare.turnstile.secret_key' => 'secret-fixture', 'cloudflare.turnstile.hostnames' => ['localhost']]);
    }

    public function test_contact_is_durable_encrypted_and_notified_only_to_operator(): void
    {
        $this->post('/contact', $this->contact())->assertSessionHasNoErrors()->assertSessionHas('contact_reference');
        $message = ContactMessage::firstOrFail();
        $this->assertSame('Ana', $message->data['first_name']);
        $raw = DB::table('contact_messages')->value('data');
        $this->assertStringNotContainsString('ana@example.test', $raw);
        $this->assertStringNotContainsString('mesaj fictiv', $raw);
        Notification::assertSentOnDemand(ContactMessageReceived::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === config('legal.operator.email'));
        $mail = (new ContactMessageReceived($message->id))->toMail(new \stdClass);
        $this->assertSame('mail.contact', $mail->view);
        $this->assertSame($message->id, $mail->viewData['reference']);
        $this->artisan('pontaj:contact', ['--resolve' => $message->id])->assertSuccessful();
        $this->assertSame('resolved', $message->refresh()->status);
        $this->get('/contact/'.$message->id)->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_contact_survives_notification_failure_and_rejects_honeypot_and_missing_fields(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Simulated queue failure'));
        $this->post('/contact', $this->contact())->assertSessionHasNoErrors()->assertSessionHas('contact_reference');
        $this->assertDatabaseCount('contact_messages', 1);
        $this->post('/contact', $this->contact(['website' => 'https://spam.example.test']))->assertSessionHasErrors('website');
        $this->post('/contact', $this->contact(['first_name' => '', 'email' => 'invalid', 'message' => 'short']))->assertSessionHasErrors(['first_name', 'email', 'message']);
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_contact_has_a_server_side_rate_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $this->contact())->assertSessionHasNoErrors();
        }
        $this->post('/contact', $this->contact())->assertStatus(429);
        $this->assertDatabaseCount('contact_messages', 5);
    }

    public function test_turnstile_is_required_on_all_three_post_endpoints_without_trusting_browser_flags(): void
    {
        $this->enableTurnstile();
        foreach (['login', 'register', 'contact'] as $path) {
            $this->post('/'.$path, [...$this->contact(), 'captcha_passed' => true])->assertSessionHasErrors('turnstile_token');
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('contact_messages', 0);
        Http::assertNothingSent();
        $this->get('/login')->assertInertia(fn ($page) => $page->where('turnstile.enabled', true)->where('turnstile.siteKey', 'site-fixture'))->assertDontSee('secret-fixture');
    }

    public function test_verified_token_is_bound_to_hostname_action_and_single_use(): void
    {
        $this->enableTurnstile();
        $sequence = Http::fakeSequence();
        foreach ([['success' => false], ['success' => true, 'hostname' => 'attacker.example', 'action' => 'contact'], ['success' => true, 'hostname' => 'localhost', 'action' => 'login'], ['success' => true]] as $reply) {
            $sequence->push($reply);
        }
        $sequence->push(['success' => true, 'hostname' => 'localhost', 'action' => 'contact']);
        for ($index = 0; $index < 4; $index++) {
            $this->post('/contact', $this->contact(['turnstile_token' => 'invalid-fixture-'.$index]))->assertSessionHasErrors('turnstile_token');
        }
        $this->post('/contact', $this->contact(['turnstile_token' => 'unique-success-fixture']))->assertSessionHasNoErrors();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.2'])->post('/contact', $this->contact(['turnstile_token' => 'unique-success-fixture']))->assertSessionHasErrors('turnstile_token');
        $this->assertDatabaseCount('contact_messages', 1);
        Http::assertSent(fn ($request) => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify' && $request['secret'] === 'secret-fixture' && ! isset($request['email']) && ! isset($request['password']));
    }

    public function test_turnstile_timeout_partial_config_and_test_keys_in_production_fail_closed(): void
    {
        $this->enableTurnstile();
        Http::fake(fn () => throw new ConnectionException('Simulated timeout'));
        $this->post('/contact', $this->contact(['turnstile_token' => 'timeout-fixture']))->assertSessionHasErrors('turnstile_token');
        config(['cloudflare.turnstile.secret_key' => '']);
        $this->post('/contact', $this->contact(['turnstile_token' => 'configuration-fixture']))->assertStatus(503);
        $this->app->instance('env', 'production');
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['cloudflare.turnstile.secret_key' => '1x0000000000000000000000000000000AA']);
        $this->post('/contact', $this->contact(['turnstile_token' => 'dummy-fixture']))->assertStatus(503);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_cloudflare_trust_is_limited_to_enabled_proxy_ranges_and_never_trusts_forwarded_host(): void
    {
        Route::middleware('web')->get('/test-proxy', fn (Request $request) => ['ip' => $request->ip(), 'secure' => $request->isSecure(), 'host' => $request->getHost()]);
        $headers = ['X-Forwarded-For' => '203.0.113.7', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example', 'CF-Connecting-IP' => '203.0.113.8'];
        config(['cloudflare.enabled' => true]);
        $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.12'])->getJson('http://localhost/test-proxy', $headers)->assertJson(['ip' => '203.0.113.7', 'secure' => true, 'host' => 'localhost']);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])->getJson('http://localhost/test-proxy', $headers)->assertJson(['ip' => '198.51.100.2', 'secure' => false, 'host' => 'localhost']);
        config(['cloudflare.enabled' => false]);
        $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.12'])->getJson('http://localhost/test-proxy', $headers)->assertJson(['ip' => '173.245.48.12', 'secure' => false]);
    }

    public function test_home_has_server_readable_content_canonical_and_honest_structured_data(): void
    {
        config(['seo.indexable' => true, 'seo.url' => 'https://ipontaj.ro', 'seo.google_verification' => 'public-verification-fixture']);
        $response = $this->get('/?utm_source=test');
        $response->assertOk()->assertSee('<h1>Pontajul la zi.', false)->assertSee('application/ld+json', false)->assertSee('https://ipontaj.ro/', false)->assertSee('public-verification-fixture');
        $response->assertDontSee('aggregateRating')->assertDontSee('utm_source=test" rel="canonical', false);
        preg_match('/<script data-inertia="seo-jsonld"[^>]*>(.*?)<\/script>/s', $response->getContent(), $matches);
        $schema = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('WebApplication', $schema['@graph'][2]['@type']);
        $this->assertSame(0, $schema['@graph'][2]['offers']['price']);
        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>https://ipontaj.ro/</loc>', false)->assertSee('<loc>https://ipontaj.ro/contact</loc>', false)->assertDontSee('/register')->assertDontSee('/dashboard');
        $this->get('/robots.txt')->assertSee('Sitemap: https://ipontaj.ro/sitemap.xml');
        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/login')->assertInertia(fn ($page) => $page->where('cookieSettings.analyticsAllowed', false));
        $this->actingAs(User::factory()->create())->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertInertia(fn ($page) => $page->where('cookieSettings.analyticsAllowed', false));
    }

    public function test_staging_is_noindex_and_csp_allows_only_configured_cloudflare_hosts(): void
    {
        config(['seo.indexable' => false]);
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/robots.txt')->assertSee('Disallow: /');
        $this->get('/sitemap.xml')->assertDontSee('<loc>', false);
        $this->app->instance('env', 'production');
        $this->enableTurnstile();
        $csp = $this->get('/login')->assertOk()->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://challenges.cloudflare.com', $csp);
        $this->assertStringNotContainsString('cloudflareinsights.com', $csp);
        config(['cloudflare.analytics_token' => 'public-analytics-fixture']);
        $this->assertStringContainsString('https://static.cloudflareinsights.com', $this->get('/login')->headers->get('Content-Security-Policy'));
    }
}
