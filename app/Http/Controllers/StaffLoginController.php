<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StaffLoginController
{
    private const ALLOWED_ROLES = ['admin', 'instructor', 'registrar', 'clinic'];

    public function create(Request $request)
    {
        $user = $request->user();
        $role = strtolower(trim((string) ($user ? $user->role : '')));

        if (in_array($role, self::ALLOWED_ROLES, true)) {
            return $this->redirectForRole($role);
        }

        if ($user) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/StaffLogin');
    }

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
                'email' => 'This secure login is only for admin, instructor, registrar, and clinic accounts.',
            ]);
        }

        return $this->redirectForRole($role);
    }

    private function redirectForRole(string $role)
    {
        if ($role === 'instructor') {
            session()->forget('instructor_verified');

            return redirect()->route('instructor.verify');
        }

        if ($role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($role === 'registrar') {
            return redirect()->route('registrar.dashboard');
        }

        return redirect()->route('clinic.dashboard');
    }
}
