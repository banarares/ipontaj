<?php

namespace App\Support;

trait PersonNames
{
    protected static function bootPersonNames(): void
    {
        static::saving(function ($person): void {
            if ($person->isDirty('name') && (! $person->exists || (! $person->isDirty('first_name') && ! $person->isDirty('last_name')))) {
                $parts = preg_split('/\s+/u', trim($person->name), 2) ?: [];
                $person->first_name = $parts[0] ?? '';
                $person->last_name = $parts[1] ?? '';
            } else {
                $person->first_name = trim($person->first_name ?? '');
                $person->last_name = trim($person->last_name ?? '');
                $person->name = trim($person->first_name.' '.$person->last_name);
            }
        });
    }
}
