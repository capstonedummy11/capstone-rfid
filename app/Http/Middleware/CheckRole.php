<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
  public function handle(Request $request, Closure $next, string $role): Response
  {
    $userRole = strtolower(trim((string) $request->user()?->role));
    $requiredRole = strtolower(trim($role));

    if (!$request->user() || $userRole !== $requiredRole) {
      abort(403, 'Unauthorized.');
    }

    return $next($request);
  }
}
