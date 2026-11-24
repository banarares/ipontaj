<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $kind
 * @property string $name
 * @property string $email
 * @property string|null $contract_reference
 * @property string|null $message
 * @property string $status
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable $due_at
 * @property CarbonImmutable|null $resolved_at
 * @property string|null $resolution
 */
class LegalRequest extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime', 'due_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }
}
