<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class StoreRootTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-root-ownership') ?? false;
    }

    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'integer', Rule::exists('users', 'user_id')->where(fn ($query) => $query->whereNull('deleted_at')->where('role', 'admin'))],
            'password' => ['required', 'string'],
            'two_factor_code' => ['nullable', 'string', 'size:6'],
            'confirmed' => ['accepted'],
        ];
    }

    protected function passedValidation(): void
    {
        $user = $this->user();
        if (! $user || ! Hash::check((string) $this->input('password'), (string) $user->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $code = (string) $this->input('two_factor_code');
            $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
            if ($code === '' || ! app(TwoFactorAuthenticationProvider::class)->verify($secret, $code)) {
                throw ValidationException::withMessages(['two_factor_code' => 'A valid two-factor code is required.']);
            }
        }
    }
}
