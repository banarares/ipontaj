<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PendingEmailVerification
{
    public function suspend(Request $request, User $user): void
    {
        $intended = $request->session()->get('url.intended');
        Auth::guard(config('fortify.guard'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('pending_verification', ['id' => $user->id, 'hash' => sha1($user->email), 'until' => now()->addHour()->timestamp]);
        if (is_string($intended) && str_starts_with($intended, url('/').'/')) {
            $request->session()->put('url.intended', $intended);
        }
    }

    public function user(Request $request): ?User
    {
        $pending = $request->session()->get('pending_verification');
        if (! is_array($pending) || ($pending['until'] ?? 0) < now()->timestamp || ! is_int($pending['id'] ?? null)) {
            return null;
        }
        $user = User::find($pending['id']);

        return $user && hash_equals(sha1($user->email), (string) ($pending['hash'] ?? '')) ? $user : null;
    }
}
