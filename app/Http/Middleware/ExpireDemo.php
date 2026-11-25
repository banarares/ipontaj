<?php

namespace App\Http\Middleware;

use App\Services\DemoWorkspaces;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ExpireDemo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && app(DemoWorkspaces::class)->expired($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sesiunea demo a expirat.', 'reason' => 'demo_expired'], 401);
            }

            return redirect('/?demo=expired');
        }

        return $next($request);
    }
}
