<?php

namespace App\Concerns;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    // @function passwordRules: Kinukuha ang password rules result para sa Password Validation Rules.
    // @useIn passwordRules: app/Actions/Fortify/ResetUserPassword.php
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', 'min:12', Password::default(), 'confirmed'];
    }

    // @function passwordValidationMessages: Kinukuha ang password validation messages result para sa Password Validation Rules.
    // @useIn passwordValidationMessages: app/Actions/Fortify/ResetUserPassword.php
    /**
     * Get the custom validation messages used for password fields.
     *
     * @return array<string, string>
     */
    protected function passwordValidationMessages(): array
    {
        return [
            'password.min' => 'Password must be at least 12 characters long.',
        ];
    }

    // @function currentPasswordRules: Kinukuha ang current password rules result para sa Password Validation Rules.
    // @useIn currentPasswordRules: app/Http/Requests/Settings/ProfileDeleteRequest.php
    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
