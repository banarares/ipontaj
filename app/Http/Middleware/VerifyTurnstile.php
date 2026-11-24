<?php

namespace App\Http\Middleware;

use App\Services\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && $request->is('login', 'register', 'contact')) {
            if (config('cloudflare.turnstile.enabled')) {
                $key = 'turnstile-attempts:'.$request->ip();
                abort_if(RateLimiter::tooManyAttempts($key, 30), 429, 'Prea multe încercări. Reîncearcă într-un minut.');
                RateLimiter::hit($key, 60);
            }
            app(Turnstile::class)->verify($request, $request->path());
        }

        return $next($request);
    }
}
