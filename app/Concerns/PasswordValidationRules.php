<?php

namespace App\Concerns;

use App\Rules\StrongPassword;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @param  array<string,string>  $context
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(array $context = []): array
    {
        return ['bail', 'required', 'string', Password::default(), new StrongPassword($context), 'confirmed'];
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
