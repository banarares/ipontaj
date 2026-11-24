<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $company_id
 * @property string $purpose
 * @property string $version
 * @property array<string,array{version:string,sha256:string,text:string}> $documents
 * @property array<string,mixed> $facts
 * @property CarbonImmutable $accepted_at
 * @property CarbonImmutable|null $confirmed_at
 */
class LegalAcceptance extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['documents' => 'array', 'facts' => 'array', 'accepted_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime'];
    }
}
