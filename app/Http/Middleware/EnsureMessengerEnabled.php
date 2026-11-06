<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMessengerEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(SystemSetting::messengerEnabledForRole($request->user()?->role), 404);

        return $next($request);
    }
}
