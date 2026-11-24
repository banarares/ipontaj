<?php

namespace App\Rules;

use App\Support\PasswordPolicy;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use ZxcvbnPhp\Zxcvbn;

class StrongPassword implements DataAwareRule, ValidationRule
{
    /** @var array<string,mixed> */
    private array $data = [];

    /** @param array<string,string> $context */
    public function __construct(private array $context = []) {}

    /** @param array<string,mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }
        if (strlen($value) > PasswordPolicy::MAX_BYTES) {
            $fail('Parola este prea lungă. Scurteaz-o puțin, păstrând cerințele de siguranță.');

            return;
        }
        if (mb_strlen($value) < PasswordPolicy::MIN_LENGTH) {
            return;
        }
        if (! preg_match('/[\pS\pP]/u', $value)) {
            $fail('Parola trebuie să conțină cel puțin un simbol, de exemplu !, @ sau #. Spațiul nu este un simbol.');

            return;
        }
        $context = [...PasswordPolicy::COMMON_INPUTS];
        foreach (['first_name', 'last_name', 'email'] as $key) {
            $text = $this->context[$key] ?? $this->data[$key] ?? '';
            if (is_string($text)) {
                $context[] = $text;
                foreach (preg_split('/[\s@._-]+/u', $text) ?: [] as $part) {
                    if (mb_strlen($part) >= 3) {
                        $context[] = $part;
                    }
                }
            }
        }
        $lower = mb_strtolower($value);
        if (preg_match('/(.)\\1{4}/u', $lower)) {
            $fail('Evită repetițiile lungi ale aceluiași caracter. Alege o combinație mai puțin previzibilă.');

            return;
        }
        $first = $this->context['first_name'] ?? $this->data['first_name'] ?? '';
        $last = $this->context['last_name'] ?? $this->data['last_name'] ?? '';
        if (is_string($first) && is_string($last) && mb_strlen($first) >= 3 && mb_strlen($last) >= 3) {
            $normalize = fn (string $text): string => mb_strtolower(preg_replace('/[^\pL\pN]/u', '', $text) ?? '');
            $normalized = $normalize($value);
            $a = $normalize($first);
            $b = $normalize($last);
            if (str_contains($normalized, $a.$b) || str_contains($normalized, $b.$a)) {
                $fail('Nu folosi numele și prenumele în parolă. Alege o combinație care nu te identifică.');

                return;
            }
        }
        $result = (new Zxcvbn)->passwordStrength($value, $context);
        if ($result['score'] < PasswordPolicy::MIN_SCORE) {
            $fail('Parola este prea previzibilă. Alege o combinație mai lungă, fără nume, date, repetiții sau secvențe ușor de ghicit.');
        }
    }
}
