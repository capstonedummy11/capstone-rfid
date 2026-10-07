<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    // @function rules: Kinukuha ang rules result para sa Password Update Request.
    // @useIn rules: Laravel FormRequest validation lifecycle
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }

    // @function messages: Kinukuha ang messages result para sa Password Update Request.
    // @useIn messages: Laravel FormRequest validation lifecycle
    /**
     * Get the validation error messages for the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->passwordValidationMessages();
    }
}
