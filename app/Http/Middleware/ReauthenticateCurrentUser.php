<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ReauthenticateCurrentUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $email = $request->input('email');

        // A cached login form must verify the password again for the current account.
        // The guest middleware still blocks switching to another account in this session.
        if ($user && is_string($email) && hash_equals(
            Str::lower(trim((string) $user->email)),
            Str::lower(trim($email)),
        )) {
            Auth::guard(config('fortify.guard', 'web'))->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $next($request);
    }
}
