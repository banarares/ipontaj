<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $time_entry_id
 * @property string $kind
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable $end_at
 * @property TimeEntry $timeEntry
 */
class TimeInterval extends Model
{
    /** @return BelongsTo<TimeEntry, $this> */
    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class);
    }

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['start_at' => 'immutable_datetime', 'end_at' => 'immutable_datetime'];
    }
}
