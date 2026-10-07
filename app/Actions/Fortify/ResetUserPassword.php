<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    // @function reset: Nire-reset ang reset user password sa Reset User Password flow.
    // @useIn reset: Laravel Fortify authentication action
    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        if (strtolower((string) $user->role) === 'console') {
            throw ValidationException::withMessages([
                'email' => 'Console accounts do not support password recovery.',
            ]);
        }

        Validator::make($input, [
            'password' => $this->passwordRules(),
        ], $this->passwordValidationMessages())->validate();

        $user->forceFill([
            'password' => $input['password'],
            'must_change_password' => false,
        ])->save();
    }
}
