<?php

namespace App\Services;

use App\Models\Company;
use App\Models\LegalAcceptance;
use App\Models\User;

class LegalAgreements
{
    public function __construct(private LegalDocuments $documents) {}

    /** @param list<string> $slugs */
    public function record(User $user, string $purpose, array $slugs, ?Company $company = null): LegalAcceptance
    {
        return LegalAcceptance::create(['user_id' => $user->id, 'company_id' => $company?->id, 'purpose' => $purpose, 'version' => config('legal.version'), 'documents' => $this->documents->snapshot($slugs), 'facts' => $this->documents->facts(), 'accepted_at' => now()]);
    }

    public function hasAccountAgreement(User $user): bool
    {
        return LegalAcceptance::where('user_id', $user->id)->where('purpose', 'account')->where('version', config('legal.version'))->exists();
    }

    public function hasCompanyAgreement(Company $company): bool
    {
        return LegalAcceptance::where('company_id', $company->id)->where('purpose', 'company')->where('version', config('legal.version'))->exists();
    }
}
