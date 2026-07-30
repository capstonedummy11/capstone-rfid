<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(
            \Laravel\Fortify\Contracts\LoginResponse::class,
            \App\Http\Responses\LoginResponse::class,
        );

        $this->app->singleton(
            \Laravel\Fortify\Contracts\LogoutResponse::class,
            \App\Http\Responses\LogoutResponse::class,
        );

        $this->app->singleton(
            \Laravel\Fortify\Contracts\PasswordResetResponse::class,
            \App\Http\Responses\PasswordResetResponse::class,
        );

        $this->app->singleton(
            \Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse::class,
            \App\Http\Responses\EmailVerificationNotificationSentResponse::class,
        );

        $this->configureActions();
        $this->configureViews();
        $this->configureAuthentication();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn(Request $request) => Inertia::render('Auth/StaffLogin', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn(Request $request) => Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn(Request $request) => Inertia::render('Auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn(Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn() => Inertia::render('auth/Register'));

        Fortify::twoFactorChallengeView(fn() => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn() => Inertia::render('auth/ConfirmPassword'));
    }

    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $email = Str::lower((string) $request->input('email'));
            $password = (string) $request->input('password');
            $user = User::query()->where('email', $email)->first();

            if (! $user || ! Hash::check($password, (string) $user->password)) {
                return null;
            }

            $role = strtolower(trim((string) $user->role));
            $path = trim($request->path(), '/');
            $staffPath = trim(config('fortify.paths.login', 'secure-route'), '/') ?: 'secure-route';

            if ($path === 'login' && ! in_array($role, ['student', 'parent'], true)) {
                throw ValidationException::withMessages([
                    'email' => 'This login is only for student and parent accounts.',
                ]);
            }

            if ($path === $staffPath && ! in_array($role, ['admin', 'instructor', 'registrar', 'clinic'], true)) {
                throw ValidationException::withMessages([
                    'email' => 'This secure login is only for admin, instructor, registrar, and clinic accounts.',
                ]);
            }

            return $user;
        });
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
