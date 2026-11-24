<?php

namespace App\Services;

use App\Models\Company;
use App\Support\CompanyCif;

class CompanyIdentity
{
    public function __construct(private CompanyRegistry $registry) {}

    /** @return array{name:string,cif:string|null,registry_verified_at:string|null} */
    public function attributes(?string $input, string $name, ?Company $existing = null): array
    {
        $cif = $input === null || trim($input) === '' ? null : CompanyCif::normalize($input);
        $name = trim($name);
        $verified = null;
        if ($existing?->is_demo || $existing?->customer_type === 'consumer') {
            return ['name' => $name, 'cif' => null, 'registry_verified_at' => null];
        }
        if ($existing && $existing->registry_verified_at && $existing->cif === $cif && $existing->name === $name) {
            $verified = $existing->registry_verified_at->toIso8601String();
        } elseif ($cif) {
            $record = $this->registry->cached($cif);
            if (($record['status'] ?? null) === 'found' && ($record['name'] ?? null) === $name) {
                $verified = $record['verified_at'] ?? null;
            }
        }

        return ['name' => $name, 'cif' => $cif, 'registry_verified_at' => $verified];
    }
}
