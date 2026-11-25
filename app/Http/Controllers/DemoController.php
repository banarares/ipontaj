<?php

namespace App\Http\Controllers;

use App\Services\DemoWorkspaces;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    public function start(Request $request, DemoWorkspaces $demos): RedirectResponse
    {
        if ($request->user()) {
            return redirect('/dashboard');
        }
        try {
            $user = $demos->start();
        } catch (LockTimeoutException) {
            $user = null;
        }
        if (! $user) {
            return redirect('/')->withErrors(['demo' => 'Toate spațiile demo sunt ocupate momentan. Revino în câteva minute sau creează un cont gratuit.']);
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/dashboard')->with('success', 'Explorează firma demo. Datele fictive sunt șterse la ieșire sau după '.$demos->lifetimeMinutes().' de minute.');
    }
}
