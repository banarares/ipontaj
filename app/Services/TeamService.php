<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamService
{
    private function lockCompany(Company $company): Company
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Company::whereKey($company->id)->update(['updated_at' => DB::raw('updated_at')]);
        }

        return Company::lockForUpdate()->findOrFail($company->id);
    }

    /** @param array{first_name:string,last_name:string,joined_on:string} $data */
    public function addManaged(Company $company, array $data, int $actor): Membership
    {
        return DB::transaction(function () use ($company, $data, $actor) {
            $company = $this->lockCompany($company);
            if ($company->occupiedSeats() >= $company->seatLimit()) {
                throw ValidationException::withMessages(['first_name' => 'Ai atins limita planului. Revocă o invitație sau dezactivează o persoană.']);
            }
            $member = $company->memberships()->create([
                'user_id' => null, 'first_name' => trim($data['first_name']), 'last_name' => trim($data['last_name']),
                'joined_on' => $data['joined_on'], 'reminders' => false,
                'managed_confirmed_by' => $actor, 'managed_confirmed_at' => now(),
            ]);
            app(AttendanceService::class)->audit($company, $actor, 'Coleg fără cont adăugat', null, [
                'membership_id' => $member->id, 'first_name' => $member->first_name, 'last_name' => $member->last_name,
                'joined_on' => $data['joined_on'], 'responsibility_confirmed' => true,
            ]);

            return $member;
        }, 3);
    }

    /** @param array{first_name:string,last_name:string,email:string} $data
     * @return array{Invitation,string} */
    public function invite(Company $company, array $data): array
    {
        return DB::transaction(function () use ($company, $data) {
            // SQLite has no SELECT FOR UPDATE. Acquire its write lock before reading
            // seat counts, so two deferred transactions cannot race to upgrade a read lock.
            $company = $this->lockCompany($company);
            $email = Str::lower($data['email']);
            if ($company->memberships()->whereHas('user', fn ($q) => $q->where('email', $email))->exists()) {
                throw ValidationException::withMessages(['email' => 'Această persoană face deja parte din echipă.']);
            }
            $existing = $company->invitations()->where('email', $email)->whereNull('revoked_at')->whereNull('accepted_at')->where('expires_at', '>', now())->first();
            if (! $existing && $company->occupiedSeats() >= $company->seatLimit()) {
                throw ValidationException::withMessages(['email' => 'Ai atins limita planului. Revocă o invitație sau dezactivează o persoană.']);
            }
            $token = Str::random(64);
            if ($existing) {
                $existing->update(['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
                $invite = $existing;
            } else {
                $invite = $company->invitations()->create(['email' => $email, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
            }

            return [$invite, $token];
        }, 3);
    }

    public function accept(string $token, User $user): Membership
    {
        return DB::transaction(function () use ($token, $user) {
            $invite = Invitation::where('token_hash', hash('sha256', $token))->firstOrFail();
            $company = Company::lockForUpdate()->findOrFail($invite->company_id);
            $invite->refresh();
            abort_unless(hash_equals($invite->email, Str::lower($user->email)), 403, 'Autentifică-te cu adresa invitată.');
            abort_if($company->is_demo || $user->is_demo, 403);
            abort_if($invite->revoked_at || $invite->expires_at->isPast(), 410, 'Invitația a expirat sau a fost revocată.');
            if ($invite->accepted_at) {
                return Membership::where('company_id', $company->id)->where('user_id', $user->id)->firstOrFail();
            }
            if ($company->occupiedSeats() > $company->seatLimit()) {
                throw ValidationException::withMessages(['invitation' => 'Firma depășește limita planului. Proprietarul trebuie să elibereze locuri.']);
            }
            $member = Membership::firstOrCreate(['company_id' => $company->id, 'user_id' => $user->id], ['joined_on' => now($company->timezone)->toDateString()]);
            $invite->update(['accepted_at' => now()]);

            return $member;
        }, 3);
    }
}
