<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password || strtolower((string) $user->role) === 'console') {
            return $next($request);
        }

        if ($request->routeIs(
            'password.first-login',
            'password.first-login.update',
            'logout',
            'password.request',
            'password.email',
            'password.reset',
            'password.update'
        )) {
            return $next($request);
        }

        return redirect()->route('password.first-login');
    }
}
