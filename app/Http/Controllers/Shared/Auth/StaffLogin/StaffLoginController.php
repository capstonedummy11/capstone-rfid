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
     * @feature     Role-Based Login and Session Protection
     * @actor       Shared / Core
     * @flow        Dito nilolog in ang staff at binabantayan ang role at browser session.
     * @uses        resources/js/pages/Shared/Auth/StaffLogin/StaffLoginPage.vue; routes/web.php: StaffLoginController::store, StudentParentLoginController::store; app/Http/Middleware/CheckRole.php
     * @related     Lahat ng protected workspace at per-login verification.
     * @disable     1) Suriin ang Role-Based Login and Session Protection callers, pending work, at dependent screens; Needs developer check: exact shared routes at background consumers.
     * @disable     2) Magdagdag at subukan ng feature-specific server guard sa named actions; panatilihin ang shared route/method para sa ibang feature. Itago pagkatapos ang controls sa `resources/js/pages/Shared/Auth/StaffLogin/StaffLoginPage.vue`.
     * @disable     3) I-check ang affected user flow, reports, pending jobs, at historical read access; huwag burahin ang existing records/files bilang bahagi ng disable.
     * @sideEffects Gumagawa/nagpapalit ng session state at activity log; login ay may role checks.
     * @dependsOn   Lahat ng protected workspace at per-login verification.
     * @performance Needs developer check: sukatin ang request/provider/worker work bago at pagkatapos; UI hide lang ay walang nakumpirmang bilis na dagdag.
     * @dataImpact  Walang data deletion sa nakasaad na disable steps; mananatili ang records/files pero maaaring hindi mabuksan sa hidden UI.
     * @reEnable    1) Ibalik ang server guard/action. 2) Ibalik ang UI controls. 3) I-test ang actor access, dependencies, pending work, at historical data.
     * @editable    Login UI: email at password lamang; route/role policy ay code/config.
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
