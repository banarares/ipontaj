<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $membership_id
 * @property string $start_on
 * @property string|null $end_on
 */
class MembershipPeriod extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
