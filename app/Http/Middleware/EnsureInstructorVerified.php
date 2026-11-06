<?php
// FEATURE:instructor-verification - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstructorVerified
{
    // @function handle: Pinoproseso ang request o event para sa Ensure Instructor Verified.
    // @useIn handle: Laravel web middleware pipeline
    public function handle(Request $request, Closure $next): Response
    {
        $role = strtolower(trim((string) $request->user()?->role));

        if ($role === 'instructor' && ! (bool) $request->session()->get('instructor_verified', false)) {
            return redirect()->route('instructor.verify');
        }

        return $next($request);
    }
}
