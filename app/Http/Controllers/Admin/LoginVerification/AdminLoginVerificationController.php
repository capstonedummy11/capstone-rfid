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
     * @feature     Admin Login Email OTP
     * @actor       Shared / Core
     * @flow        Bawat tunay na Admin password login ay may bagong email OTP challenge.
     * @uses        resources/js/pages/Admin/LoginVerification/AdminLoginVerificationPage.vue; routes/admin.php: AdminLoginVerificationController::show, AdminLoginVerificationController::verify, AdminLoginVerificationController::resend
     * @related     Lahat ng protected Admin pages.
     * @disable     1) Suriin ang Admin Login Email OTP callers, pending work, at dependent screens; Needs developer check: huwag alisin ang OTP routes habang EnsureAdminLoginOtpVerified ay nagre-redirect dito; i-test muna ang kapalit na access policy.
     * @disable     2) Magdagdag at subukan ng feature-specific server guard sa named actions; panatilihin ang shared route/method para sa ibang feature. Itago pagkatapos ang controls sa `resources/js/pages/Admin/LoginVerification/AdminLoginVerificationPage.vue`.
     * @disable     3) I-check ang affected user flow, reports, pending jobs, at historical read access; huwag burahin ang existing records/files bilang bahagi ng disable.
     * @sideEffects Nag-iisyu ng hashed session-bound challenge at OTP email attempt; verification ay nagbabago ng session state.
     * @dependsOn   Lahat ng protected Admin pages.
     * @performance Needs developer check: sukatin ang request/provider/worker work bago at pagkatapos; UI hide lang ay walang nakumpirmang bilis na dagdag.
     * @dataImpact  Walang data deletion sa nakasaad na disable steps; mananatili ang records/files pero maaaring hindi mabuksan sa hidden UI.
     * @reEnable    1) Ibalik ang server guard/action. 2) Ibalik ang UI controls. 3) I-test ang actor access, dependencies, pending work, at historical data.
     * @editable    Needs developer check: walang no-code OTP email template editor; timing ay server config.
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
