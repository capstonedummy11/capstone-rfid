<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRootOverrideRequest extends FormRequest
{
    // @function authorize: Sinusuri ang authorize condition para sa Store Root Override Request.
    // @useIn authorize: Laravel FormRequest validation lifecycle
    public function authorize(): bool
    {
        return $this->user()?->can('request-root-override') ?? false;
    }

    // @function rules: Kinukuha ang rules result para sa Store Root Override Request.
    // @useIn rules: Laravel FormRequest validation lifecycle
    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'integer', Rule::exists('users', 'user_id')->where(fn ($query) => $query->whereNull('deleted_at')->where('role', 'admin'))],
            'reason' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }
}
