<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdleTimeout
{
    public const LAST_ACTIVITY_AT = 'auth.idle_last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));

        if (! $user || ! $this->appliesTo($role)) {
            return $next($request);
        }

        $now = now()->timestamp;
        $lastActivityAt = (int) $request->session()->get(self::LAST_ACTIVITY_AT, 0);
        $expiresAfter = ((int) config('idle_timeout.idle_minutes', 15) * 60)
            + (int) config('idle_timeout.warning_seconds', 30);

        if ($lastActivityAt > 0 && ($now - $lastActivityAt) >= $expiresAfter) {
            return $this->expiredResponse($request, $role);
        }

        if ($lastActivityAt === 0 || $this->isUserActivityRequest($request)) {
            $request->session()->put(self::LAST_ACTIVITY_AT, $now);
        }

        return $next($request);
    }

    private function appliesTo(string $role): bool
    {
        if (in_array($role, config('idle_timeout.exempt_roles', ['clinic', 'console']), true)) {
            return false;
        }

        return in_array($role, config('idle_timeout.enabled_roles', []), true);
    }

    private function isUserActivityRequest(Request $request): bool
    {
        if ($request->header('X-Background-Poll') === '1') {
            return false;
        }

        foreach (config('idle_timeout.background_routes', []) as $routeName) {
            if ($request->routeIs($routeName)) {
                return false;
            }
        }

        return true;
    }

    private function expiredResponse(Request $request, string $role): Response
    {
        Auth::guard(config('auth.defaults.guard'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = 'You were logged out due to inactivity.';
        $request->session()->flash('success', $message);
        $loginRoute = in_array($role, ['admin', 'instructor'], true) ? 'staff.login' : 'landingPage';
        $loginUrl = route($loginRoute);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code' => 'idle_timeout',
                'login_url' => $loginUrl,
            ], 401);
        }

        return redirect()->to($loginUrl)->with('success', $message);
    }
}
