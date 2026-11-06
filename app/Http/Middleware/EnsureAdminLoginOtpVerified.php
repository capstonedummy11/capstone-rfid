<?php

// FEATURE:admin-login-otp - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Http\Middleware;

use App\Services\Auth\AdminLoginOtpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminLoginOtpVerified
{
    // @function __construct: Tinatanggap ang dependencies ng Ensure Admin Login Otp Verified sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang EnsureAdminLoginOtpVerified
    public function __construct(private readonly AdminLoginOtpService $otp) {}

    // @function handle: Pinoproseso ang request o event para sa Ensure Admin Login Otp Verified.
    // @useIn handle: Laravel web middleware pipeline
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $this->otp->requiresVerification($request, $user) || $request->routeIs('admin.login-verification.*', 'logout', 'staff.login.store')
            || ($user->must_change_password && $request->routeIs('password.first-login', 'password.first-login.update'))) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Admin login OTP verification is required.'], 423);
        }

        return redirect()->route('admin.login-verification.show');
    }
}
