<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureParentPortalEnabled
{
    // @function handle: Pinoproseso ang request o event para sa Ensure Parent Portal Enabled.
    // @useIn handle: Laravel web middleware pipeline
    public function handle(Request $request, Closure $next): Response
    {
        $isParent = strtolower(trim((string) $request->user()?->role)) === 'parent';

        if ($isParent && ! SystemSetting::boolean(SystemSetting::PARENT_PORTAL_ENABLED, false)) {
            return redirect()->route('landingPage')->withErrors([
                'email' => __('auth.failed'),
            ]);
        }

        return $next($request);
    }
}
