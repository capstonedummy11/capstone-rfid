<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PreventConsolePasswordReset
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('password.email')) {
            $email = Str::lower(trim((string) $request->input('email')));
            $isConsole = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereRaw('LOWER(role) = ?', ['console'])
                ->exists();

            if ($isConsole) {
                return back()->with('status', trans('passwords.sent'));
            }
        }

        return $next($request);
    }
}
