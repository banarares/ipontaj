<?php

namespace App\Http\Middleware;

use App\Services\LegalDocuments;
use App\Services\SeoMetadata;
use App\Services\Turnstile;
use App\Support\PasswordPolicy;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            Inertia::clearHistory();
        }

        $response = parent::handle($request, $next);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'head' => fn () => app(SeoMetadata::class)->head($request),
            'turnstile' => fn () => app(Turnstile::class)->publicConfig(),
            'cookieSettings' => fn () => ['version' => config('legal.version').':'.(config('cloudflare.analytics_token') ? 'analytics' : 'necessary'), 'analyticsToken' => config('cloudflare.analytics_token'), 'analyticsAllowed' => ! $request->user() && $request->routeIs('home', 'legal.contact')],
            'passwordPolicy' => PasswordPolicy::frontend(),
            'legal' => fn () => app(LegalDocuments::class)->publicData(),
            'flash' => ['success' => fn () => $request->session()->get('success'), 'invitation_url' => fn () => $request->session()->get('invitation_url'), 'legal_request' => fn () => $request->session()->get('legal_request'), 'contact_reference' => fn () => $request->session()->get('contact_reference')],
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
