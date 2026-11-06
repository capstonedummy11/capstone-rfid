<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    // @function profileRules: Kinukuha ang profile rules result para sa Profile Validation Rules.
    // @useIn profileRules: app/Actions/Fortify/CreateNewUser.php
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    // @function nameRules: Kinukuha ang name rules result para sa Profile Validation Rules.
    // @useIn nameRules: ProfileValidationRules::profileRules (app/Concerns/ProfileValidationRules.php)
    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    // @function emailRules: Kinukuha ang email rules result para sa Profile Validation Rules.
    // @useIn emailRules: ProfileValidationRules::profileRules (app/Concerns/ProfileValidationRules.php)
    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)->whereNull('deleted_at')
                : Rule::unique(User::class)->whereNull('deleted_at')->ignore($userId, (new User)->getKeyName()),
        ];
    }
}
