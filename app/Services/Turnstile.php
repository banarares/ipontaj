<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class Turnstile
{
    /** @return array{enabled:bool,siteKey:string|null} */
    public function publicConfig(): array
    {
        return ['enabled' => (bool) config('cloudflare.turnstile.enabled'), 'siteKey' => config('cloudflare.turnstile.site_key')];
    }

    public function verify(Request $request, string $action): void
    {
        if (! config('cloudflare.turnstile.enabled')) {
            return;
        }
        $secret = (string) config('cloudflare.turnstile.secret_key');
        $siteKey = (string) config('cloudflare.turnstile.site_key');
        // Cloudflare's public dummy keys must never silently disable production protection.
        $dummy = preg_match('/^[123]x0+/', $siteKey) === 1 && in_array($secret, ['1x0000000000000000000000000000000AA', '2x0000000000000000000000000000000AA', '3x0000000000000000000000000000000AA'], true);
        abort_if($secret === '' || $siteKey === '' || (app()->isProduction() && (preg_match('/^[123]x0+/', $secret) || preg_match('/^[123]x0+/', $siteKey))), 503, 'Verificarea de securitate nu este disponibilă momentan. Încearcă mai târziu sau contactează office@ipontaj.ro.');
        $dummy = $dummy && app()->environment('local', 'testing');
        $data = $request->validate(['turnstile_token' => 'required|string|max:2048'], ['turnstile_token.required' => 'Finalizează verificarea de securitate.']);
        $token = $data['turnstile_token'];
        $key = 'turnstile:used:'.hash('sha256', $token);
        $lock = Cache::lock($key.':lock', 15);
        if (! $lock->get()) {
            $this->fail();
        }
        try {
            if (! $dummy && Cache::has($key)) {
                $this->fail();
            }
            try {
                $response = Http::asForm()->connectTimeout(3)->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['secret' => $secret, 'response' => $token, 'remoteip' => $request->ip()]);
            } catch (\Throwable) {
                $this->fail('Verificarea de securitate nu a răspuns. Reîncearcă verificarea.');
            }
            $result = $response->json();
            if (! $response->successful() || ! is_array($result) || ($result['success'] ?? false) !== true || (! $dummy && (($result['action'] ?? null) !== $action || ! in_array($result['hostname'] ?? null, config('cloudflare.turnstile.hostnames'), true)))) {
                $this->fail();
            }
            if (! $dummy) {
                Cache::put($key, true, now()->addMinutes(6));
            }
        } finally {
            $lock->release();
        }
    }

    private function fail(string $message = 'Verificarea de securitate a expirat sau nu este validă. Reîncearcă verificarea.'): never
    {
        throw ValidationException::withMessages(['turnstile_token' => $message]);
    }
}
