<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property array{first_name:string,last_name:string,email:string,subject:string,message:string} $data
 * @property string $status
 */
class ContactMessage extends Model
{
    use HasUuids;

    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['data' => 'encrypted:array'];
    }
}
