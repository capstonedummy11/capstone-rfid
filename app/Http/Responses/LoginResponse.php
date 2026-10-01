<?php

namespace App\Http\Responses;

use App\Services\Auth\AdminLoginOtpService;
use App\Support\AuthenticatedSession;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function __construct(private readonly AdminLoginOtpService $adminOtp) {}

    public function toResponse($request)
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));

        Log::info('LoginResponse user context', [
            'user_id' => $user?->user_id,
            'email' => $user?->email,
            'raw_role' => $user?->role,
            'normalized_role' => $role,
        ]);

        if ($role === 'instructor') {
            $request->session()->forget('instructor_verified');
            Log::info('LoginResponse redirect', ['target' => 'instructor.verify']);

            return redirect()->route('instructor.verify');
        }

        if ($role === 'admin') {
            if (! AuthenticatedSession::hasIdentity($request)) {
                AuthenticatedSession::issue($request, $user);
            }
            $sent = $this->adminOtp->issue($request, $user);
            Log::info('LoginResponse redirect', ['target' => 'admin.login-verification.show']);

            $response = redirect()->route('admin.login-verification.show');

            return $sent
                ? $response->with('success', 'A verification code was sent to your Admin email address.')
                : $response->withErrors(['otp' => 'The verification email could not be sent. Use resend to try again.']);
        }

        if ($role === 'registrar') {
            Log::info('LoginResponse redirect', ['target' => 'registrar.dashboard']);

            return redirect()->route('registrar.dashboard');
        }

        if ($role === 'clinic') {
            Log::info('LoginResponse redirect', ['target' => 'clinic.dashboard']);

            return redirect()->route('clinic.dashboard');
        }

        if ($role === 'console') {
            Log::info('LoginResponse redirect', ['target' => 'attendanceControlPanel']);

            return redirect()->route('attendanceControlPanel');
        }

        if (in_array($role, ['student', 'parent'], true)) {
            Log::info('LoginResponse redirect', ['target' => 'student-parent.dashboard']);

            return redirect()->route('student-parent.dashboard');
        }

        Log::info('LoginResponse redirect', ['target' => config('fortify.home', '/')]);

        return redirect()->intended(config('fortify.home', '/'));
    }
}
