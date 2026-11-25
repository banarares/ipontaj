<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PendingEmailVerification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationController extends Controller
{
    public function notice(Request $request, PendingEmailVerification $pending): Response|RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect('/dashboard');
        }

        return Inertia::render('auth/VerifyEmail', ['status' => $request->session()->get('status'), 'canResend' => $pending->user($request) !== null]);
    }

    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = DB::transaction(function () use ($id, $hash) {
            $user = User::lockForUpdate()->findOrFail($id);
            abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
            if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            return $user;
        }, 3);
        $request->session()->forget('pending_verification');
        if ($request->user()?->id === $user->id) {
            return redirect('/dashboard?verified=1');
        }

        return redirect('/login')->with('status', 'Adresa de email a fost confirmată. Acum poți intra în cont.');
    }

    public function resend(Request $request, PendingEmailVerification $pending): RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect('/dashboard');
        }
        $user = $pending->user($request);
        if (! $user || $user->hasVerifiedEmail()) {
            return redirect('/login')->with('status', 'Intră în cont pentru a solicita un nou email de confirmare.');
        }
        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
