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
 * @property int $membership_id
 * @property CarbonImmutable $date
 * @property string|null $absence
 * @property int $minutes
 * @property CarbonImmutable|null $confirmed_at
 * @property int|null $confirmed_by
 * @property CarbonImmutable|null $approved_at
 * @property int|null $approved_by
 * @property string|null $note
 * @property Collection<int, TimeInterval> $intervals
 * @property Membership $membership
 */
class TimeEntry extends Model
{
    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['date' => 'date', 'confirmed_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    /** @return HasMany<TimeInterval, $this> */
    public function intervals(): HasMany
    {
        return $this->hasMany(TimeInterval::class);
    }

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
