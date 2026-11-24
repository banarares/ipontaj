<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property string $role
 * @property bool $active
 * @property bool $tracked
 * @property bool $reminders
 * @property CarbonImmutable $joined_on
 * @property CarbonImmutable|null $left_on
 * @property Company $company
 * @property User $user
 * @property Collection<int, TimeEntry> $entries
 * @property Collection<int, MembershipPeriod> $periods
 */
class Membership extends Model
{
    public function activeOn(string $date): bool
    {
        if ($date < $this->joined_on->toDateString() || ($this->left_on && $date > $this->left_on->toDateString())) {
            return false;
        }

        return $this->relationLoaded('periods')
            ? $this->periods->contains(fn (MembershipPeriod $period) => substr($period->start_on, 0, 10) <= $date && (! $period->end_on || substr($period->end_on, 0, 10) >= $date))
            : $this->periods()->whereDate('start_on', '<=', $date)->where(fn ($q) => $q->whereNull('end_on')->orWhereDate('end_on', '>=', $date))->exists();
    }

    /** @return HasMany<MembershipPeriod, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(MembershipPeriod::class);
    }

    protected static function booted(): void
    {
        static::created(function (Membership $m) {
            $m->periods()->create(['start_on' => $m->joined_on->toDateString(), 'end_on' => $m->left_on?->toDateString()]);
        });
        static::updated(function (Membership $m) {
            if ($m->wasChanged('active')) {
                if ($m->active) {
                    $m->periods()->create(['start_on' => now($m->company->timezone)->toDateString()]);
                } else {
                    $m->periods()->whereNull('end_on')->update(['end_on' => now($m->company->timezone)->toDateString()]);
                }
            }
        });
    }

    protected $attributes = ['active' => true, 'tracked' => true, 'reminders' => true, 'role' => 'employee'];

    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['active' => 'boolean', 'tracked' => 'boolean', 'reminders' => 'boolean', 'joined_on' => 'date', 'left_on' => 'date'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
