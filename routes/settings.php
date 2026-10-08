<?php

use App\Http\Controllers\Shared\Settings\Password\PasswordController;
use App\Http\Controllers\Shared\Settings\Profile\ProfileController;
use App\Http\Controllers\Shared\Settings\TwoFactor\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    // Profile Settings
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Profile Settings
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Password Settings
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    // Appearance Settings
    Route::get('settings/appearance', function () {
        return Inertia::render('Shared/Settings/Appearance/AppearancePage', [
            'title' => 'Appearance Settings',
        ]);
    })->name('appearance.edit');

    // Two-Factor Settings
    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
