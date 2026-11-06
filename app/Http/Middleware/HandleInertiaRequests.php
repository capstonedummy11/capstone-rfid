<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Support\AuthenticatedSession;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    // @function version: Binubuo ang version string para sa Handle Inertia Requests.
    // @useIn version: Inertia middleware lifecycle sa page requests
    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    // @function share: Kinukuha ang share result para sa Handle Inertia Requests.
    // @useIn share: Inertia middleware lifecycle sa page requests
    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'appVersion' => config('app.version'),
            'auth' => [
                'user' => $request->user(),
            ],
            'idleTimeout' => fn () => $this->idleTimeoutSettings($request),
            'featureSettings' => fn () => SystemSetting::featureFlags(),
            'flash' => [
                'success' => session('success'),
                'product' => fn () => $request->session()->get('product'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'title' => session('title', 'Dashboard'),
        ];
    }

    private function idleTimeoutSettings(Request $request): array
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));
        $exemptRoles = config('idle_timeout.exempt_roles', ['clinic', 'console']);
        $enabledRoles = config('idle_timeout.enabled_roles', []);
        $enabled = $user
            && in_array($role, $enabledRoles, true)
            && ! in_array($role, $exemptRoles, true);

        return [
            'enabled' => (bool) $enabled,
            'idle_timeout_seconds' => (int) config('idle_timeout.idle_minutes', 15) * 60,
            'warning_countdown_seconds' => (int) config('idle_timeout.warning_seconds', 30),
            'activity_ping_seconds' => (int) config('idle_timeout.activity_ping_seconds', 60),
            'exempt_roles' => $exemptRoles,
            'session_key' => (string) $request->session()->get(AuthenticatedSession::LOGIN_ID, ''),
            'keep_alive_url' => route('session.keep-alive'),
            'logout_url' => route('session.idle-logout'),
            'login_url' => in_array($role, ['admin', 'instructor'], true)
                ? route('staff.login')
                : route('landingPage'),
        ];
    }
}
