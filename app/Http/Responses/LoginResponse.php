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

    if ($role === 'admin') {
      Log::info('LoginResponse redirect', ['target' => 'admin.dashboard']);
      return redirect()->route('admin.dashboard');
    }

    Log::info('LoginResponse redirect', ['target' => config('fortify.home', '/')]);
    return redirect()->intended(config('fortify.home', '/'));
  }
}
