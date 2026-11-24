<?php

namespace App\Support;

final class PasswordPolicy
{
    public const MIN_LENGTH = 10;

    public const MAX_BYTES = 72;

    public const MIN_SCORE = 3;

    public const COMMON_INPUTS = ['ipontaj', 'parola', 'parolamea', 'parolasecreta', 'bineaivenit', 'romania'];

    /** @return array{minLength:int,maxBytes:int,minScore:int} */
    public static function frontend(): array
    {
        return ['minLength' => self::MIN_LENGTH, 'maxBytes' => self::MAX_BYTES, 'minScore' => self::MIN_SCORE];
    }
}
