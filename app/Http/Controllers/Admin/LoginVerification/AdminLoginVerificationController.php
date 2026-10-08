<?php

namespace App\Http\Controllers\Admin\LoginVerification;

use App\Http\Controllers\Controller;

use App\Services\Auth\AdminLoginOtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminLoginVerificationController extends Controller
{
    // @function show: Ibinabalik ang Auth/AdminLoginVerification page at data para sa request.
    // @useIn show: routes/web.php:112 (show)
    /**
     * @feature   Admin Login Email OTP
     * @actor     Shared / Core
     * @flow      Bawat tunay na Admin password login ay may bagong email OTP challenge.
     * @uses      resources/js/pages/Admin/LoginVerification/AdminLoginVerificationPage.vue; routes/web.php: AdminLoginVerificationController::show, AdminLoginVerificationController::verify, AdminLoginVerificationController::resend
     * @related   Authentication, Attendance, Reports
     * @disable   1) I-comment out ang routes/web.php: AdminLoginVerificationController::show, AdminLoginVerificationController::verify, AdminLoginVerificationController::resend.
     * @disable   2) Itago ang action sa resources/js/pages/Admin/LoginVerification/AdminLoginVerificationPage.vue; kung may menu link, alisin ito sa resources/js/layouts/AuthNavbar.vue.
     * @disable   3) Ihinto ang app/Http/Controllers/Admin/LoginVerification/AdminLoginVerificationController.php: AdminLoginVerificationController::show matapos alisin ang routes. Side effect: mawawala ang admin login email otp.
     */
    public function show(Request $request, AdminLoginOtpService $otp)
    {
        abort_unless(strtolower((string) $request->user()?->role) === 'admin', 403);
        if (! $otp->requiresVerification($request, $request->user())) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('Admin/LoginVerification/AdminLoginVerificationPage', [
            'email' => $this->maskEmail((string) $request->user()->email),
            'expiresAt' => $request->session()->get(AdminLoginOtpService::EXPIRES_AT),
            'resendAfterSeconds' => $otp->secondsUntilResend($request),
            'status' => $request->session()->get('success'),
        ]);
    }

    // @function verify: Vini-verify ang admin login verification sa Admin Login Verification flow.
    // @useIn verify: routes/web.php:114 (verify)
    public function verify(Request $request, AdminLoginOtpService $otp)
    {
        $validated = $request->validate(['otp' => ['required', 'digits:6']]);
        $otp->verify($request, $request->user(), $validated['otp']);

        return redirect()->route('admin.dashboard')->with('success', 'Admin login verified.');
    }

    // @function resend: Kinukuha ang resend result para sa Admin Login Verification.
    // @useIn resend: routes/web.php:118 (resend)
    public function resend(Request $request, AdminLoginOtpService $otp)
    {
        $seconds = $otp->secondsUntilResend($request);
        if ($seconds > 0) {
            throw ValidationException::withMessages(['otp' => "Wait {$seconds} seconds before requesting another code."]);
        }

        if (! $otp->issue($request, $request->user())) {
            throw ValidationException::withMessages(['otp' => AdminLoginOtpService::DELIVERY_ERROR]);
        }

        return back()->with('success', 'A new verification code was sent. The previous code no longer works.');
    }

    // @function maskEmail: Binubuo ang mask email string para sa Admin Login Verification.
    // @useIn maskEmail: AdminLoginVerificationController::show (app/Http/Controllers/Admin/LoginVerification/AdminLoginVerificationController.php)
    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($name, 0, min(2, mb_strlen($name)));

        return $visible.str_repeat('*', max(3, mb_strlen($name) - mb_strlen($visible))).'@'.$domain;
    }
}
