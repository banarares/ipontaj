<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class CompanyCif
{
    public static function normalize(mixed $value): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages(['cif' => 'Introdu CIF-ul firmei, cu sau fără prefixul RO.']);
        }
        $normalized = mb_strtoupper(preg_replace('/\s/u', '', trim($value)) ?? '');
        if (str_starts_with($normalized, 'RO')) {
            $normalized = substr($normalized, 2);
        }
        if (! preg_match('/^\d{2,10}$/D', $normalized) || str_starts_with($normalized, '0')) {
            throw ValidationException::withMessages(['cif' => 'CIF-ul trebuie să aibă între 2 și 10 cifre, fără zero la început. Poți include prefixul RO.']);
        }

        return $normalized;
    }
}
