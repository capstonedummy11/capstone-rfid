<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    // @function register: Nirerehistro ang dependencies ng Fortify Service.
    // @useIn register: Laravel service provider lifecycle
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    // @function boot: Nirerehistro ang startup behavior ng Fortify Service.
    // @useIn boot: Laravel service provider lifecycle
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $path = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false);

            return rtrim((string) config('app.url', 'http://localhost'), '/').'/'.ltrim($path, '/');
        });

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

    // @function configureActions: Pinoproseso ang configure actions para sa Fortify Service.
    // @useIn configureActions: FortifyServiceProvider::boot (app/Providers/FortifyServiceProvider.php)
    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    // @function configureViews: Ibinabalik ang Auth/StaffLogin page at data para sa request.
    // @useIn configureViews: FortifyServiceProvider::boot (app/Providers/FortifyServiceProvider.php)
    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('Shared/Auth/StaffLogin/StaffLoginPage', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('Shared/Auth/ResetPassword/ResetPasswordPage', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('Shared/Auth/ForgotPassword/ForgotPasswordPage', [
            'status' => $request->session()->get('status'),
            'backUrl' => $request->query('from') === 'staff'
                ? route('staff.login')
                : route('landingPage'),
            'backLabel' => $request->query('from') === 'staff'
                ? 'Back to Staff login'
                : 'Back to Student / Parent login',
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('Public/Register/RegisterPage'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('Shared/Auth/TwoFactorChallenge/TwoFactorChallengePage'));

        Fortify::confirmPasswordView(fn () => Inertia::render('Shared/Auth/ConfirmPassword/ConfirmPasswordPage'));
    }

    // @function configureAuthentication: Kinukuha ang configure authentication result para sa Fortify Service.
    // @useIn configureAuthentication: FortifyServiceProvider::boot (app/Providers/FortifyServiceProvider.php)
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

    // @function configureRateLimiting: Kinukuha ang configure rate limiting result para sa Fortify Service.
    // @useIn configureRateLimiting: FortifyServiceProvider::boot (app/Providers/FortifyServiceProvider.php)
    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('root-ownership', function (Request $request) {
            return Limit::perMinute((int) config('root_ownership.rate_limit_per_minute', 5))
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('admin-login-otp-send', function (Request $request) {
            return Limit::perMinute(3)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('admin-login-otp-verify', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('first-login-password', function (Request $request) {
            return Limit::perMinute(15)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
