<?php

namespace App\Http\Controllers\Shared\Auth;

use App\Http\Middleware\EnforceIdleTimeout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IdleSessionController
{
    public function keepAlive(Request $request): JsonResponse
    {
        $request->session()->put(EnforceIdleTimeout::LAST_ACTIVITY_AT, now()->timestamp);

        return response()->json([
            'ok' => true,
            'last_activity_at' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $role = strtolower(trim((string) $request->user()?->role));
        $loginRoute = in_array($role, ['admin', 'instructor'], true) ? 'staff.login' : 'landingPage';
        $message = 'You were logged out due to inactivity.';

        Auth::guard(config('auth.defaults.guard'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('success', $message);

        return response()->json([
            'ok' => true,
            'message' => $message,
            'login_url' => route($loginRoute),
        ]);
    }
}
