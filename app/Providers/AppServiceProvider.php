<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
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
        URL::forceRootUrl(rtrim((string) config('app.url', 'http://localhost'), '/'));

        Gate::define('manage-root-ownership', fn ($user) => strtolower((string) $user->role) === 'admin' && (bool) $user->is_root_admin && ! $user->trashed());
        Gate::define('request-root-override', fn ($user) => strtolower((string) $user->role) === 'admin' && ! $user->trashed());
        Gate::define('approve-root-override', fn ($user) => strtolower((string) $user->role) === 'admin' && ! $user->trashed());

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
