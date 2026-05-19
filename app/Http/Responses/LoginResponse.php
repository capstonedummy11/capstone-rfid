<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
  public function toResponse($request)
  {
    $user = $request->user();
    $role = strtolower(trim((string) $user?->role));

    Log::info('LoginResponse user context', [
      'user_id' => $user?->user_id,
      'email' => $user?->email,
      'raw_role' => $user?->role,
      'normalized_role' => $role,
    ]);

    if (in_array($role, ['admin', 'instructor'], true)) {
      Log::info('LoginResponse redirect', ['target' => 'admin.dashboard']);
      return redirect()->route('admin.dashboard');
    }

    if ($role === 'clinic') {
      Log::info('LoginResponse redirect', ['target' => 'clinic.dashboard']);
      return redirect()->route('clinic.dashboard');
    }

    Log::info('LoginResponse redirect', ['target' => config('fortify.home', '/')]);
    return redirect()->intended(config('fortify.home', '/'));
  }
}
