<?php

namespace App\Http\Middleware;

use App\Services\Auth\AdminLoginOtpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminLoginOtpVerified
{
    public function __construct(private readonly AdminLoginOtpService $otp) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $this->otp->requiresVerification($request, $user) || $request->routeIs('admin.login-verification.*', 'logout')
            || ($user->must_change_password && $request->routeIs('password.first-login', 'password.first-login.update'))) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Admin login OTP verification is required.'], 423);
        }

        return redirect()->route('admin.login-verification.show');
    }
}
