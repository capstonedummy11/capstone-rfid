<?php

namespace App\Http\Middleware;

use App\Support\AuthenticatedSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticatedSessionIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (Auth::guard(config('auth.defaults.guard'))->viaRemember()) {
            $role = strtolower(trim((string) $user->role));

            Auth::guard(config('auth.defaults.guard'))->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Your browser session expired. Please sign in again.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 401);
            }

            $loginRoute = match (true) {
                $role === 'console' => 'attendanceControlPanel.login',
                in_array($role, ['admin', 'instructor', 'registrar', 'clinic'], true) => 'staff.login',
                default => 'landingPage',
            };

            return redirect()->route($loginRoute)->withErrors(['email' => $message]);
        }

        if (! AuthenticatedSession::hasIdentity($request)) {
            AuthenticatedSession::issue($request, $user);

            return $next($request);
        }

        if (AuthenticatedSession::belongsTo($request, $user)) {
            return $next($request);
        }

        $role = strtolower(trim((string) $user->role));

        Auth::guard(config('auth.defaults.guard'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = 'This browser session changed unexpectedly. Please sign in again.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        $loginRoute = match (true) {
            $role === 'console' => 'attendanceControlPanel.login',
            in_array($role, ['admin', 'instructor', 'registrar', 'clinic'], true) => 'staff.login',
            default => 'landingPage',
        };

        return redirect()->route($loginRoute)->withErrors(['email' => $message]);
    }
}
