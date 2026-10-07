<?php
// FEATURE:admin-login-otp - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Services\Auth;

use App\Mail\AdminLoginOtpMail;
use App\Models\User;
use App\Support\AuthenticatedSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AdminLoginOtpService
{
    public const DELIVERY_ERROR = 'The verification code could not be emailed right now. Select Resend code to try again. If it still fails, contact your system administrator.';

    public const REQUIRED_LOGIN_ID = 'admin_login_otp.required_login_id';

    public const VERIFIED_LOGIN_ID = 'admin_login_otp.verified_login_id';

    public const USER_ID = 'admin_login_otp.user_id';

    public const HASH = 'admin_login_otp.hash';

    public const EXPIRES_AT = 'admin_login_otp.expires_at';

    public const RESEND_AT = 'admin_login_otp.resend_at';

    public const ATTEMPTS = 'admin_login_otp.attempts';

    // @function issue: Sinusuri ang issue condition para sa Admin Login Otp.
    // @useIn issue: app/Http/Controllers/StaffLoginController.php
    public function issue(Request $request, User $user): bool
    {
        abort_unless(strtolower((string) $user->role) === 'admin', 403);

        $loginId = (string) $request->session()->get(AuthenticatedSession::LOGIN_ID);
        abort_if($loginId === '', 409, 'The authenticated login session is missing. Please sign in again.');

        $code = (string) random_int(100000, 999999);
        $expiresMinutes = max(1, (int) config('admin_login_otp.expires_minutes', 10));
        $resendSeconds = max(1, (int) config('admin_login_otp.resend_seconds', 60));

        $request->session()->put([
            self::REQUIRED_LOGIN_ID => $loginId,
            self::USER_ID => (string) $user->getAuthIdentifier(),
            self::HASH => Hash::make($code),
            self::EXPIRES_AT => now()->addMinutes($expiresMinutes)->timestamp,
            self::RESEND_AT => now()->addSeconds($resendSeconds)->timestamp,
            self::ATTEMPTS => 0,
        ]);
        $request->session()->forget(self::VERIFIED_LOGIN_ID);

        try {
            Mail::to($user->email)->send(new AdminLoginOtpMail($code, $expiresMinutes));
        } catch (\Throwable $exception) {
            $request->session()->forget([self::HASH, self::EXPIRES_AT, self::RESEND_AT, self::ATTEMPTS]);
            Log::warning('Admin login OTP email could not be sent.', [
                'user_id' => $user->getAuthIdentifier(),
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    // @function verify: Vini-verify ang admin login otp sa Admin Login Otp flow.
    // @useIn verify: app/Http/Controllers/AdminLoginVerificationController.php
    public function verify(Request $request, User $user, string $code): void
    {
        $this->assertBoundToCurrentLogin($request, $user);
        $hash = (string) $request->session()->get(self::HASH, '');
        $expiresAt = (int) $request->session()->get(self::EXPIRES_AT, 0);
        $attempts = (int) $request->session()->get(self::ATTEMPTS, 0);
        $maxAttempts = max(1, (int) config('admin_login_otp.max_attempts', 5));

        if ($hash === '' || now()->timestamp > $expiresAt) {
            $this->forgetChallenge($request);
            throw ValidationException::withMessages(['otp' => 'The verification code expired. Request a new code.']);
        }

        if ($attempts >= $maxAttempts) {
            $this->forgetChallenge($request);
            throw ValidationException::withMessages(['otp' => 'Too many incorrect attempts. Request a new code.']);
        }

        if (! Hash::check($code, $hash)) {
            $nextAttempt = $attempts + 1;
            if ($nextAttempt >= $maxAttempts) {
                $this->forgetChallenge($request);
                throw ValidationException::withMessages(['otp' => 'Too many incorrect attempts. Request a new code.']);
            }

            $request->session()->put(self::ATTEMPTS, $nextAttempt);
            throw ValidationException::withMessages(['otp' => 'The verification code is incorrect.']);
        }

        $loginId = (string) $request->session()->get(AuthenticatedSession::LOGIN_ID);
        $this->forgetChallenge($request);
        $request->session()->put(self::VERIFIED_LOGIN_ID, $loginId);
    }

    // @function requiresVerification: Sinusuri kung verification para sa Admin Login Otp.
    // @useIn requiresVerification: app/Http/Controllers/AdminLoginVerificationController.php
    public function requiresVerification(Request $request, User $user): bool
    {
        if (strtolower((string) $user->role) !== 'admin') {
            return false;
        }

        $loginId = (string) $request->session()->get(AuthenticatedSession::LOGIN_ID, '');
        $requiredLoginId = (string) $request->session()->get(self::REQUIRED_LOGIN_ID, '');

        return $loginId !== ''
            && hash_equals($loginId, $requiredLoginId)
            && ! hash_equals($loginId, (string) $request->session()->get(self::VERIFIED_LOGIN_ID, ''));
    }

    // @function secondsUntilResend: Kinukuha ang seconds until resend result para sa Admin Login Otp.
    // @useIn secondsUntilResend: app/Http/Controllers/AdminLoginVerificationController.php
    public function secondsUntilResend(Request $request): int
    {
        return max(0, (int) $request->session()->get(self::RESEND_AT, 0) - now()->timestamp);
    }

    // @function clear: Nililinis ang admin login otp sa Admin Login Otp flow.
    // @useIn clear: app/Http/Responses/LogoutResponse.php
    public function clear(Request $request): void
    {
        $request->session()->forget([
            self::REQUIRED_LOGIN_ID, self::VERIFIED_LOGIN_ID, self::USER_ID,
            self::HASH, self::EXPIRES_AT, self::RESEND_AT, self::ATTEMPTS,
        ]);
    }

    // @function assertBoundToCurrentLogin: Sini-check ang bound to current login sa Admin Login Otp flow.
    // @useIn assertBoundToCurrentLogin: AdminLoginOtpService::verify (app/Services/Auth/AdminLoginOtpService.php)
    private function assertBoundToCurrentLogin(Request $request, User $user): void
    {
        $loginId = (string) $request->session()->get(AuthenticatedSession::LOGIN_ID, '');
        abort_unless(
            $loginId !== ''
            && hash_equals($loginId, (string) $request->session()->get(self::REQUIRED_LOGIN_ID, ''))
            && hash_equals((string) $user->getAuthIdentifier(), (string) $request->session()->get(self::USER_ID, '')),
            403,
            'This verification code does not belong to the current login.',
        );
    }

    // @function forgetChallenge: Pinoproseso ang forget challenge para sa Admin Login Otp.
    // @useIn forgetChallenge: AdminLoginOtpService::verify (app/Services/Auth/AdminLoginOtpService.php)
    private function forgetChallenge(Request $request): void
    {
        $request->session()->forget([self::HASH, self::EXPIRES_AT, self::RESEND_AT, self::ATTEMPTS]);
    }
}
