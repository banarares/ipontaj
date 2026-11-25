<?php

namespace App\Http\Middleware;

use App\Services\LegalAgreements;
use App\Services\SeoMetadata;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class LegalSafety
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction()) {
            Vite::useCspNonce();
            // Operator confirmations are reported by pontaj:legal:check.
            // Runtime checkout checks configuration, verification, terms and price.
            $user = $request->user();
            if ($user && ! $user->is_demo && $request->is('dashboard', 'today', 'history', 'team', 'month', 'settings', 'billing', 'entries*', 'invitations*', 'schedules', 'members/*', 'months/*', 'company/*')) {
                $agreements = app(LegalAgreements::class);
                $membership = $user->memberships()->where('active', true)->where('company_id', $request->session()->get('company_id'))->first() ?? $user->memberships()->where('active', true)->first();
                if (! $agreements->hasAccountAgreement($user) || ($membership?->role === 'owner' && ! $agreements->hasCompanyAgreement($membership->company))) {
                    return redirect('/acorduri');
                }
            }
        }
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if (! app(SeoMetadata::class)->indexable($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if (app()->isProduction()) {
            $turnstile = config('cloudflare.turnstile.enabled') ? ' https://challenges.cloudflare.com' : '';
            $analytics = config('cloudflare.analytics_token') ? ' https://static.cloudflareinsights.com' : '';
            $beacon = config('cloudflare.analytics_token') ? ' https://cloudflareinsights.com' : '';
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-".Vite::cspNonce()."'".$turnstile.$analytics."; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'".$turnstile.$beacon."; frame-src 'self'".$turnstile."; frame-ancestors 'none'; object-src 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests");
            if ($request->isSecure()) {
                $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
            }
        }

        return $response;
    }
}
