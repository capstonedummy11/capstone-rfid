<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;

class PasswordResetResponse implements PasswordResetResponseContract
{
    public function toResponse($request)
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower((string) $request->input('email'))])
            ->first();
        $role = strtolower((string) $user?->role);
        $route = in_array($role, ['admin', 'instructor', 'registrar', 'clinic'], true)
            ? 'staff.login'
            : 'landingPage';

        return redirect()->route($route)->with('status', trans('passwords.reset'));
    }
}
