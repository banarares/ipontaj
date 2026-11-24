<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $approval_version
 * @property int $id
 * @property int $company_id
 * @property string $month
 * @property CarbonImmutable|null $approved_at
 * @property int|null $approved_by
 */
class MonthClosure extends Model
{
    protected $attributes = ['approval_version' => 0];

    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }
}
