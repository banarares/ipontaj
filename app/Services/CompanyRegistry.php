<?php

namespace App\Services;

use App\Support\CompanyCif;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class CompanyRegistry
{
    /** @return array{status:string,cif:string,name?:string,verified_at?:string} */
    public function lookup(string $input): array
    {
        $cif = CompanyCif::normalize($input);
        if ($cached = $this->cached($cif)) {
            return $cached;
        }
        $result = Cache::lock('anaf:request:lock', 15)->get(function () use ($cif): array {
            if ($cached = $this->cached($cif)) {
                return $cached;
            }
            if (RateLimiter::tooManyAttempts('anaf:request:second', 1)) {
                return ['status' => 'busy', 'cif' => $cif];
            }
            RateLimiter::hit('anaf:request:second', 1);
            $date = now('Europe/Bucharest')->toDateString();
            try {
                $response = Http::connectTimeout(3)->timeout(10)->acceptJson()->asJson()->post(config('services.anaf.endpoint'), [['cui' => (int) $cif, 'data' => $date]]);
            } catch (ConnectionException) {
                return ['status' => 'unavailable', 'cif' => $cif];
            }
            if (! $response->successful()) {
                return ['status' => 'unavailable', 'cif' => $cif];
            }
            $payload = $response->json();
            if (! is_array($payload) || ! is_array($payload['found'] ?? null) || ! is_array($payload['notFound'] ?? null)) {
                return ['status' => 'unavailable', 'cif' => $cif];
            }
            foreach ($payload['found'] as $item) {
                $general = is_array($item) ? ($item['date_generale'] ?? null) : null;
                if (! is_array($general) || ! is_scalar($general['cui'] ?? null) || (string) $general['cui'] !== $cif) {
                    continue;
                }
                $name = $general['denumire'] ?? null;
                if (! is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 255 || ($general['data'] ?? null) !== $date) {
                    return ['status' => 'unavailable', 'cif' => $cif];
                }
                $result = ['status' => 'found', 'cif' => $cif, 'name' => trim($name), 'verified_at' => now()->toIso8601String()];
                Cache::put($this->key($cif), $result, now()->addHours(24));

                return $result;
            }
            if (in_array($cif, array_map(fn ($value) => is_scalar($value) ? (string) $value : '', $payload['notFound']), true)) {
                $result = ['status' => 'not_found', 'cif' => $cif];
                Cache::put($this->key($cif), $result, now()->addMinutes(5));

                return $result;
            }

            return ['status' => 'unavailable', 'cif' => $cif];
        });

        return $this->record($result, $cif) ?? ['status' => 'busy', 'cif' => $cif];
    }

    /** @return array{status:string,cif:string,name?:string,verified_at?:string}|null */
    public function cached(string $cif): ?array
    {
        $result = $this->record(Cache::get($this->key($cif)), $cif);

        return $result && in_array($result['status'], ['found', 'not_found'], true) ? $result : null;
    }

    /** @return array{status:string,cif:string,name?:string,verified_at?:string}|null */
    private function record(mixed $value, string $cif): ?array
    {
        if (! is_array($value) || ($value['cif'] ?? null) !== $cif) {
            return null;
        }
        $status = $value['status'] ?? null;
        if ($status === 'found' && is_string($value['name'] ?? null) && is_string($value['verified_at'] ?? null)) {
            return ['status' => 'found', 'cif' => $cif, 'name' => $value['name'], 'verified_at' => $value['verified_at']];
        }
        if (in_array($status, ['not_found', 'busy', 'unavailable'], true)) {
            return ['status' => $status, 'cif' => $cif];
        }

        return null;
    }

    private function key(string $cif): string
    {
        return 'anaf:company:'.now('Europe/Bucharest')->toDateString().':'.$cif;
    }
}
