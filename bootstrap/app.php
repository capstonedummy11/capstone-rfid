<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordSystemActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__ . '/../routes/web.php',
            __DIR__ . '/../routes/settings.php',
        ],
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('attendance-control-panel*')) {
                return route('attendanceControlPanel.login');
            }

            if ($request->is('admin*', 'clinic*', 'registrar*', 'instructor*')) {
                return route('login');
            }

            if ($request->is('user/*', 'email/*', 'two-factor-challenge')) {
                return route('login');
            }

            return route('landingPage');
        });

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            RecordSystemActivity::class,
            \App\Http\Middleware\PreventConsolePasswordReset::class,
            \App\Http\Middleware\EnsurePasswordIsChanged::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'instructor.verified' => \App\Http\Middleware\EnsureInstructorVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
