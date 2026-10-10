<?php

namespace App\Http\Controllers\Shared\Auth\StaffLogin;

use App\Services\Auth\AdminLoginOtpService;
use App\Support\AuthenticatedSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

// Used before authentication by staff login; the submitted account determines its role and later access.
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

        return Inertia::render('Shared/Auth/StaffLogin/StaffLoginPage');
    }

    // @function store: Pinoproseso ang bagong Staff Login record.
    // @useIn store: routes/web.php:93
    /**
    * WHAT IT DOES: Sinusuri ang staff login at binubuksan ang page para sa tamang role.
    * WHO USES IT: Admin, Instructor, Clinic, at Registrar.
    * WHAT HAPPENS: Kapag tama ang account, makakapag-sign in ang staff at mase-save ang login activity.
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
                : $response->withErrors(['otp' => AdminLoginOtpService::DELIVERY_ERROR]);
        }

        return $this->redirectForRole($role);
    }

    // @function redirectForRole: Kinukuha ang redirect for role result para sa Staff Login.
    // @useIn redirectForRole: StaffLoginController::create (app/Http/Controllers/Shared/Auth/StaffLogin/StaffLoginController.php)
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
