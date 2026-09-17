<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StudentParentLoginController
{
    private const ALLOWED_ROLES = ['student', 'parent'];

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();
        $role = strtolower(trim((string) ($user ? $user->role : '')));

        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This login is only for student and parent accounts.',
            ]);
        }

        if ($role === 'parent' && ! SystemSetting::boolean(SystemSetting::PARENT_PORTAL_ENABLED, false)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return redirect()->route('student-parent.dashboard');
    }
}
