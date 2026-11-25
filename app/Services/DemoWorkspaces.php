<?php

namespace App\Services;

use App\Models\Company;
use App\Models\LegalAcceptance;
use App\Models\Membership;
use App\Models\User;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoWorkspaces
{
    private const LOCK = 'pontaj:demo:workspaces';

    public function lifetimeMinutes(): int
    {
        return max(5, min(1440, (int) config('ipontaj.demo.session_minutes', 60)));
    }

    public function expired(User $user): bool
    {
        return $user->is_demo && (! $user->created_at || $user->created_at->lte(now()->subMinutes($this->lifetimeMinutes())));
    }

    public function start(): ?User
    {
        return Cache::lock(self::LOCK, 120)->block(5, function (): ?User {
            $this->cleanup($this->expiredCompanies());
            if (Company::where('is_demo', true)->count() >= max(1, min(200, (int) config('ipontaj.demo.max_workspaces', 20)))) {
                return null;
            }

            return DB::transaction(fn () => $this->seed());
        });
    }

    /** @return array{companies:int,users:int} */
    public function prune(bool $dryRun = false): array
    {
        return Cache::lock(self::LOCK, 120)->block(5, fn () => $this->cleanup($this->expiredCompanies(), $dryRun));
    }

    public function discard(User $user): void
    {
        if (! $user->is_demo) {
            return;
        }
        Cache::lock(self::LOCK, 120)->block(5, function () use ($user): void {
            $companies = $this->disposableCompanies()->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('role', 'owner'));
            $this->cleanup($companies, extraUserIds: [$user->id]);
        });
    }

    /** @return Builder<Company> */
    private function disposableCompanies(): Builder
    {
        // Never treat a flag alone as permission to delete a real customer's workspace.
        return Company::where('is_demo', true)->whereNull('stripe_id')->whereNull('cif')
            ->whereDoesntHave('subscriptions')
            ->whereDoesntHave('memberships.user', fn ($q) => $q->where('is_demo', false))
            ->whereNotIn('id', LegalAcceptance::whereNotNull('company_id')->select('company_id'));
    }

    /** @return Builder<Company> */
    private function expiredCompanies(): Builder
    {
        return $this->disposableCompanies()->where('created_at', '<=', now()->subMinutes($this->lifetimeMinutes()));
    }

    /**
     * @param  Builder<Company>  $companies
     * @param  list<int>  $extraUserIds
     * @return array{companies:int,users:int}
     */
    private function cleanup(Builder $companies, bool $dryRun = false, array $extraUserIds = []): array
    {
        return DB::transaction(function () use ($companies, $dryRun, $extraUserIds): array {
            $companyIds = $companies->lockForUpdate()->pluck('id')->all();
            $memberUserIds = Membership::whereIntegerInRaw('company_id', $companyIds)->whereNotNull('user_id')->pluck('user_id')->all();
            $userIds = User::where('is_demo', true)
                ->whereNotIn('id', LegalAcceptance::whereNotNull('user_id')->select('user_id'))
                ->where(fn ($q) => $q->where('created_at', '<=', now()->subMinutes($this->lifetimeMinutes()))->orWhereIntegerInRaw('id', [...$memberUserIds, ...$extraUserIds]))
                ->whereDoesntHave('memberships', fn ($q) => $q->whereIntegerNotInRaw('company_id', $companyIds))
                ->lockForUpdate()->pluck('id')->all();
            if (! $dryRun) {
                Company::whereIntegerInRaw('id', $companyIds)->delete();
                // Database sessions have no user FK; remove these explicitly too.
                DB::table('sessions')->whereIntegerInRaw('user_id', $userIds)->delete();
                User::whereIntegerInRaw('id', $userIds)->whereDoesntHave('memberships')->delete();
            }

            return ['companies' => count($companyIds), 'users' => count($userIds)];
        });
    }

    private function seed(): User
    {
        $user = User::create(['first_name' => 'Andrei', 'last_name' => 'Popescu', 'email' => 'demo-'.Str::random(32).'@example.test', 'password' => Str::random(40), 'is_demo' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $company = Company::create(['name' => 'Atelier Studio SRL', 'is_demo' => true, 'reminders' => false]);
        $start = Carbon::now($company->timezone)->startOfMonth();
        $today = Carbon::now($company->timezone)->startOfDay();
        $company->schedules()->create(['effective_from' => $start->toDateString(), 'weekdays' => [1, 2, 3, 4, 5], 'start_time' => '09:00', 'end_time' => '17:00']);
        $people = [
            ['user_id' => $user->id, 'role' => 'owner'],
            ['first_name' => 'Maria', 'last_name' => 'Ionescu'],
            ['first_name' => 'Vlad', 'last_name' => 'Dumitrescu'],
            ['first_name' => 'Ioana', 'last_name' => 'Marinescu'],
        ];
        foreach ($people as $i => $person) {
            $member = $company->memberships()->create([...$person, 'joined_on' => $start->toDateString(), 'reminders' => false, 'managed_confirmed_at' => $i ? now() : null, 'managed_confirmed_by' => $i ? $user->id : null]);
            for ($day = $start; $day <= $today; $day = $day->addDay()) {
                if ($day->isWeekend() || ($i === 0 && $day->isSameDay($today)) || ($i === 2 && ($day->isSameDay($today) || $day->isSameDay($today->subDay())))) {
                    continue;
                }
                $absence = $i === 3 && $day->isSameDay($today) ? 'CO' : null;
                $entry = $member->entries()->create(['company_id' => $company->id, 'date' => $day->toDateString(), 'minutes' => $absence ? 0 : 480, 'confirmed_at' => $i ? null : now(), 'confirmed_by' => $i ? null : $user->id, 'approved_at' => $i && ! $absence ? now() : null, 'approved_by' => $i && ! $absence ? $user->id : null, 'absence' => $absence]);
                if (! $absence) {
                    $entry->intervals()->create(['kind' => 'work', 'start_at' => $day->setTime(9, 0)->utc(), 'end_at' => $day->setTime(17, 0)->utc()]);
                }
            }
        }

        return $user;
    }
}
