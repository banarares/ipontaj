<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $membership_id
 * @property list<int> $weekdays
 * @property CarbonImmutable $effective_from
 * @property string $start_time
 * @property string $end_time
 */
class Schedule extends Model
{
    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['weekdays' => 'array', 'effective_from' => 'date'];
    }
}
