<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
  public function handle(Request $request, Closure $next, string ...$roles): Response
  {
    $userRole = strtolower(trim((string) $request->user()?->role));
    $allowedRoles = collect($roles)
      ->flatMap(fn (string $role) => explode(',', $role))
      ->map(fn (string $role) => strtolower(trim($role)))
      ->filter()
      ->all();

    if (!$request->user() || !in_array($userRole, $allowedRoles, true)) {
      abort(403, 'Unauthorized.');
    }

    return $next($request);
  }
}
