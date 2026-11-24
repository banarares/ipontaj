<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanyContext
{
    public function membership(Request $request): Membership
    {
        $query = $request->user()->memberships()->where('active', true);
        $member = (clone $query)->where('company_id', $request->session()->get('company_id'))->first() ?? $query->first();
        abort_unless($member !== null, 409, 'Configurează mai întâi firma.');
        $request->session()->put('company_id', $member->company_id);
        Gate::authorize('view', $member->company);

        return $member;
    }

    public function owner(Request $request): Company
    {
        $company = $this->membership($request)->company;
        Gate::authorize('update', $company);

        return $company;
    }
}
