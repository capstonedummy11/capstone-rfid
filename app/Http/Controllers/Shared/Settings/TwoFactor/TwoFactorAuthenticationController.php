<?php

namespace App\Http\Controllers\Shared\Settings\TwoFactor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

// Used by authenticated, verified users; the settings routes do not restrict access by role.
class TwoFactorAuthenticationController extends Controller implements HasMiddleware
{
    // @function middleware: Kinukuha ang middleware result para sa Two Factor Authentication.
    // @useIn middleware: routes/settings.php
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
            ? [new Middleware('password.confirm', only: ['show'])]
            : [];
    }

    // @function show: Ibinabalik ang settings/TwoFactor page at data para sa request.
    // @useIn show: routes/settings.php:31 (two-factor.show)
    /**
     * Show the user's two-factor authentication settings page.
     */
    public function show(TwoFactorAuthenticationRequest $request): Response
    {
        $request->ensureStateIsValid();

        return Inertia::render('Shared/Settings/TwoFactor/TwoFactorPage', [
            'title' => 'Two-Factor Authentication',
            'twoFactorEnabled' => $request->user()->hasEnabledTwoFactorAuthentication(),
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
        ]);
    }
}
