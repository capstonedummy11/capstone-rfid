<?php
// FEATURE:authentication - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Support\AuthenticatedSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StudentParentLoginController
{
    private const ALLOWED_ROLES = ['student', 'parent'];

    // @function store: Pinoproseso ang bagong Student Parent Login record.
    // @useIn store: routes/web.php:86
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, false)) {
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

        AuthenticatedSession::issue($request, $user);

        return redirect()->route('student-parent.dashboard');
    }
}
