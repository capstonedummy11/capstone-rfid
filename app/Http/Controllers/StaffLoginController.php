<?php

namespace App\Http\Controllers;

use App\Services\Auth\AdminLoginOtpService;
use App\Support\AuthenticatedSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StaffLoginController
{
    private const ALLOWED_ROLES = ['admin', 'instructor', 'registrar', 'clinic'];

    // @function create: Ibinabalik ang Auth/StaffLogin page at data para sa request.
    // @useIn create: routes/web.php:81 (staff.login)
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

    // @function store: Pinoproseso ang bagong Staff Login record.
    // @useIn store: routes/web.php:93
    /**
     * @feature   Role-Based Login and Session Protection
     * @actor     Shared / Core
     * @flow      Dito nilolog in ang staff at binabantayan ang role at browser session.
     * @uses      resources/js/pages/Auth/StaffLogin.vue; routes/web.php: StaffLoginController::store, StudentParentLoginController::store; app/Http/Middleware/CheckRole.php
     * @related   Authentication, Attendance, Reports
     * @disable   1) I-comment out ang routes/web.php: StaffLoginController::store at StudentParentLoginController::store.
     * @disable   2) Itago ang action sa resources/js/pages/Auth/StaffLogin.vue; kung may menu link, alisin ito sa resources/js/layouts/AuthNavbar.vue.
     * @disable   3) Ihinto ang app/Http/Controllers/StaffLoginController.php: store at app/Http/Controllers/StudentParentLoginController.php: store matapos alisin ang routes. Huwag alisin ang app/Http/Middleware/CheckRole.php: handle habang may authenticated routes; side effect: walang bagong staff o portal login.
     */
    public function store(Request $request, AdminLoginOtpService $adminOtp)
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
                'email' => 'This secure login is only for admin, instructor, registrar, and clinic accounts.',
            ]);
        }

        AuthenticatedSession::issue($request, $user);

        if ($role === 'admin') {
            $sent = $adminOtp->issue($request, $user);
            $response = redirect()->route('admin.login-verification.show');

            return $sent
                ? $response->with('success', 'A verification code was sent to your Admin email address.')
                : $response->withErrors(['otp' => 'The verification email could not be sent. Use resend to try again.']);
        }

        return $this->redirectForRole($role);
    }

    // @function redirectForRole: Kinukuha ang redirect for role result para sa Staff Login.
    // @useIn redirectForRole: StaffLoginController::create (app/Http/Controllers/StaffLoginController.php)
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
