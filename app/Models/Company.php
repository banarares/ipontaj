<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Laravel\Cashier\Billable;

/**
 * @property int $id
 * @property string|null $cif
 * @property CarbonImmutable|null $registry_verified_at
 * @property string $name
 * @property string $customer_type
 * @property string $timezone
 * @property bool $is_demo
 * @property bool $reminders
 * @property string|null $accountant_email
 * @property CarbonImmutable|null $accountant_confirmed_at
 * @property string|null $stripe_id
 * @property \Illuminate\Database\Eloquent\Collection<int, Membership> $memberships
 * @property \Illuminate\Database\Eloquent\Collection<int, Invitation> $invitations
 * @property \Illuminate\Database\Eloquent\Collection<int, Schedule> $schedules
 * @property \Illuminate\Database\Eloquent\Collection<int, TimeEntry> $entries
 */
class Company extends Model
{
    use Billable;

    protected $hidden = ['stripe_id', 'pm_type', 'pm_last_four'];

    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['registry_verified_at' => 'datetime', 'is_demo' => 'boolean', 'reminders' => 'boolean', 'accountant_confirmed_at' => 'datetime'];
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /** @return HasMany<Schedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return Collection<int, User> */
    public function owners(): Collection
    {
        return $this->memberships()->where('role', 'owner')->with('user')->get()->pluck('user');
    }

    public function stripeEmail(): ?string
    {
        return $this->owners()->first()?->email;
    }

    public function seatLimit(): int
    {
        return $this->is_demo ? 5 : (! config('ipontaj.billing_enabled') ? config('ipontaj.self_hosted_seat_limit') : config('ipontaj.plans.'.($this->subscribed('default') ? 'team' : 'free').'.seats'));
    }

    public function occupiedSeats(): int
    {
        return $this->memberships()->where('active', true)->where('tracked', true)->count() + $this->invitations()->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count();
    }
}
