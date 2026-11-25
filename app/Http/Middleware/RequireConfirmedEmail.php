<?php

namespace App\Http\Middleware;

use App\Services\PendingEmailVerification;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireConfirmedEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->suspend($request) && ! $request->is('email/verify*', 'email/verification-notification', 'logout')) {
            return redirect()->route('verification.notice');
        }
        $intended = $request->session()->get('url.intended');
        $response = $next($request);
        // Fortify authenticates inside login/registration/2FA/passkey controllers.
        // End an unverified session before its response reaches the browser.
        if ($this->suspend($request)) {
            // Fortify's response consumes intended before the suspended session is returned.
            if (is_string($intended) && str_starts_with($intended, url('/').'/')) {
                $request->session()->put('url.intended', $intended);
            }

            return redirect()->route('verification.notice');
        }

        return $response;
    }

    private function suspend(Request $request): bool
    {
        $user = $request->user();
        if (! $user || $user->is_demo || $user->hasVerifiedEmail()) {
            return false;
        }
        app(PendingEmailVerification::class)->suspend($request, $user);

        return true;
    }
}
