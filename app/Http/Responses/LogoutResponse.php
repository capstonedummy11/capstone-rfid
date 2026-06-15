<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
  public function toResponse($request)
  {
    $request->session()->forget([
      'instructor_verified',
      'instructor_login_otp',
      'instructor_login_otp_expires_at',
    ]);

    return redirect()->route('landingPage');
  }
}
