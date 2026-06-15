<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstructorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $role = strtolower(trim((string) $request->user()?->role));

        if ($role === 'instructor' && ! (bool) $request->session()->get('instructor_verified', false)) {
            return redirect()->route('instructor.verify');
        }

        return $next($request);
    }
}
