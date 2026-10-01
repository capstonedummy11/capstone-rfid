<?php

namespace App\Http\Controllers;

use App\Services\Auth\AdminLoginOtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminLoginVerificationController extends Controller
{
    public function show(Request $request, AdminLoginOtpService $otp)
    {
        abort_unless(strtolower((string) $request->user()?->role) === 'admin', 403);
        if (! $otp->requiresVerification($request, $request->user())) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('Auth/AdminLoginVerification', [
            'email' => $this->maskEmail((string) $request->user()->email),
            'expiresAt' => $request->session()->get(AdminLoginOtpService::EXPIRES_AT),
            'resendAfterSeconds' => $otp->secondsUntilResend($request),
            'status' => $request->session()->get('success'),
        ]);
    }

    public function verify(Request $request, AdminLoginOtpService $otp)
    {
        $validated = $request->validate(['otp' => ['required', 'digits:6']]);
        $otp->verify($request, $request->user(), $validated['otp']);

        return redirect()->route('admin.dashboard')->with('success', 'Admin login verified.');
    }

    public function resend(Request $request, AdminLoginOtpService $otp)
    {
        $seconds = $otp->secondsUntilResend($request);
        if ($seconds > 0) {
            throw ValidationException::withMessages(['otp' => "Wait {$seconds} seconds before requesting another code."]);
        }

        if (! $otp->issue($request, $request->user())) {
            throw ValidationException::withMessages(['otp' => 'The verification email could not be sent. Please try again.']);
        }

        return back()->with('success', 'A new verification code was sent. The previous code no longer works.');
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($name, 0, min(2, mb_strlen($name)));

        return $visible.str_repeat('*', max(3, mb_strlen($name) - mb_strlen($visible))).'@'.$domain;
    }
}
