<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRootOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('request-root-override') ?? false;
    }

    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'integer', Rule::exists('users', 'user_id')->where(fn ($query) => $query->whereNull('deleted_at')->where('role', 'admin'))],
            'reason' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }
}
