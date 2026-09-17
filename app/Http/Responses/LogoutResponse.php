<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
  public function toResponse($request)
  {
    $role = strtolower(trim((string) $request->user()?->role));

    $request->session()->forget([
      'instructor_verified',
      'instructor_login_otp',
      'instructor_login_otp_expires_at',
    ]);

    if (in_array($role, ['admin', 'clinic', 'registrar', 'instructor'], true)) {
      return redirect()->route('staff.login');
    }

    return redirect()->route('landingPage');
  }
}
