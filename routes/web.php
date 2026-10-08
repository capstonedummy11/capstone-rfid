<?php

// Public and authentication routes; role routes are loaded below.

use App\Http\Controllers\Public\Registration\AuthController;
use App\Http\Controllers\Shared\Auth\FirstLoginPassword\FirstLoginPasswordController;
use App\Http\Controllers\Shared\RootOwnership\RootOwnershipController;
use App\Http\Controllers\Shared\Auth\StaffLogin\StaffLoginController;
use App\Http\Controllers\StudentParent\Login\StudentParentLoginController;
use App\Http\Middleware\ReauthenticateCurrentUser;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$staffLoginPath = '/'.(trim(config('fortify.paths.login', 'secure-route'), '/') ?: 'secure-route');

// Public Pages
Route::get('/', function (Request $request) {
    $role = strtolower(trim((string) $request->user()?->role));

    if (in_array($role, ['admin', 'instructor'], true)) {
        return redirect()->route('admin.dashboard');
    }
    if ($role === 'clinic') {
        return redirect()->route('clinic.dashboard');
    }
    if ($role === 'console') {
        return redirect()->route('attendanceControlPanel');
    }
    if ($role === 'registrar') {
        return redirect()->route('registrar.dashboard');
    }
    if ($role === 'student' || ($role === 'parent' && SystemSetting::boolean(SystemSetting::PARENT_PORTAL_ENABLED, false))) {
        return redirect()->route('student-parent.dashboard');
    }

    return Inertia::render('StudentParent/Login/StudentParentLoginPage');
})->name('landingPage');
Route::redirect('/home', '/')->name('home');
Route::inertia('/about', 'Public/About/AboutPage')->name('about');
Route::middleware(['auth', 'throttle:root-ownership'])->group(function () {
    // Root Ownership Links
    Route::get('/root-ownership/transfers/{transfer}/accept', [RootOwnershipController::class, 'acceptShow'])->name('root-ownership.accept.show');
    Route::post('/root-ownership/transfers/{transfer}/accept', [RootOwnershipController::class, 'accept'])->middleware('signed')->name('root-ownership.accept.store');
});
Route::middleware('throttle:root-ownership')->group(function () {
    // Root Ownership Links
    Route::get('/root-ownership/transfers/{transfer}/cancel', [RootOwnershipController::class, 'cancelShow'])->name('root-ownership.cancel.show');
    Route::post('/root-ownership/transfers/{transfer}/cancel', [RootOwnershipController::class, 'cancelFromLink'])->middleware('signed')->name('root-ownership.cancel.store');
});

// Login
Route::redirect('/student-parent-login', '/')->name('studentParentLogin');
Route::get('/login', fn () => redirect()->route('landingPage'))->name('login');

// Staff Login
Route::get($staffLoginPath, [StaffLoginController::class, 'create'])
    ->name('staff.login');
if ($staffLoginPath !== '/secure-login') {
    // Login
    Route::redirect('/secure-login', $staffLoginPath)->name('staff.login.legacy');
}
Route::post('/login', [StudentParentLoginController::class, 'store'])
    ->middleware(array_filter([
        ReauthenticateCurrentUser::class,
        'guest:'.config('fortify.guard'),
        config('fortify.limiters.login') ? 'throttle:'.config('fortify.limiters.login') : null,
    ]))
    ->name('student-parent.login.store');

// Staff Login
Route::post($staffLoginPath, [StaffLoginController::class, 'store'])
    ->middleware(array_filter([
        ReauthenticateCurrentUser::class,
        'guest:'.config('fortify.guard'),
        config('fortify.limiters.login') ? 'throttle:'.config('fortify.limiters.login') : null,
    ]))
    ->name('staff.login.store');
Route::middleware('auth')->group(function () {
    // First-Login Password
    Route::get('/first-login/password', [FirstLoginPasswordController::class, 'edit'])->name('password.first-login');
    Route::put('/first-login/password', [FirstLoginPasswordController::class, 'update'])
        ->middleware('throttle:first-login-password')
        ->name('password.first-login.update');
});

// Registration
Route::inertia('/register', 'Public/Register/RegisterPage')->name('register');

// Dashboard Redirect
Route::get('/dashboard', function (Request $request) {
    $role = strtolower(trim((string) $request->user()?->role));

    if (in_array($role, ['admin', 'instructor'], true)) {
        return redirect()->route('admin.dashboard');
    }
    if ($role === 'clinic') {
        return redirect()->route('clinic.dashboard');
    }
    if ($role === 'console') {
        return redirect()->route('attendanceControlPanel');
    }
    if ($role === 'registrar') {
        return redirect()->route('registrar.dashboard');
    }
    if ($role === 'student' || ($role === 'parent' && SystemSetting::boolean(SystemSetting::PARENT_PORTAL_ENABLED, false))) {
        return redirect()->route('student-parent.dashboard');
    }

    return redirect()->route('landingPage');
})->middleware('auth')->name('dashboard');

// Registration
Route::post('/register', [AuthController::class, 'register'])->name('register.store');

require __DIR__.'/admin.php';
require __DIR__.'/shared.php';
require __DIR__.'/attendance-console.php';
require __DIR__.'/instructor.php';
require __DIR__.'/registrar.php';
require __DIR__.'/admin-instructor.php';
require __DIR__.'/clinic.php';
require __DIR__.'/student-parent.php';
