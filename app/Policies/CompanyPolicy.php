<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $company->memberships()->where('user_id', $user->id)->where('active', true)->exists();
    }

    public function update(User $user, Company $company): bool
    {
        return $company->memberships()->where('user_id', $user->id)->where('active', true)->where('role', 'owner')->exists();
    }
}
