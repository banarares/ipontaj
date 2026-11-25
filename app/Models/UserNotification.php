<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id */
class UserNotification extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
