<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureParentPortalEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $isParent = strtolower(trim((string) $request->user()?->role)) === 'parent';

        if ($isParent && ! SystemSetting::boolean(SystemSetting::PARENT_PORTAL_ENABLED, false)) {
            return redirect()->route('landingPage')->withErrors([
                'email' => 'The parent portal is currently disabled by the administrator.',
            ]);
        }

        return $next($request);
    }
}
