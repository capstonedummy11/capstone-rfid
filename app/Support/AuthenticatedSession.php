<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuthenticatedSession
{
    public const USER_ID = 'auth_session.user_id';

    public const LOGIN_ID = 'auth_session.login_id';

    public const ISSUED_AT = 'auth_session.issued_at';

    // @function issue: Pinoproseso ang issue para sa Authenticated Session.
    // @useIn issue: app/Http/Controllers/StudentParentLoginController.php
    public static function issue(Request $request, Authenticatable $user): void
    {
        $request->session()->put([
            self::USER_ID => (string) $user->getAuthIdentifier(),
            self::LOGIN_ID => (string) Str::uuid(),
            self::ISSUED_AT => now()->toIso8601String(),
        ]);
    }

    // @function hasIdentity: Sinusuri kung identity para sa Authenticated Session.
    // @useIn hasIdentity: app/Http/Middleware/EnsureAuthenticatedSessionIdentity.php
    public static function hasIdentity(Request $request): bool
    {
        return $request->session()->has(self::USER_ID)
            && $request->session()->has(self::LOGIN_ID);
    }

    // @function belongsTo: Sinusuri ang belongs to condition para sa Authenticated Session.
    // @useIn belongsTo: app/Http/Middleware/EnsureAuthenticatedSessionIdentity.php
    public static function belongsTo(Request $request, Authenticatable $user): bool
    {
        return hash_equals(
            (string) $request->session()->get(self::USER_ID, ''),
            (string) $user->getAuthIdentifier(),
        );
    }
}
