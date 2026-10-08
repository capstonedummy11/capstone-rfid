<?php

// Instructor verification routes.

use App\Http\Controllers\Instructor\Verification\InstructorVerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('instructor')
    ->middleware(['auth', 'role:instructor'])
    ->name('instructor.')
    ->group(function () {
        // Instructor Login Verification
        Route::get('/verify', [InstructorVerificationController::class, 'show'])->name('verify');
        Route::post('/verify/face', [InstructorVerificationController::class, 'verifyFace'])->name('verify.face');
        Route::post('/verify/otp/send', [InstructorVerificationController::class, 'sendOtp'])->name('verify.otp.send');
        Route::post('/verify/otp', [InstructorVerificationController::class, 'verifyOtp'])->name('verify.otp');
        Route::post('/verify/security/setup', [InstructorVerificationController::class, 'setupSecurity'])->name('verify.security.setup');
        Route::post('/verify/security', [InstructorVerificationController::class, 'verifySecurity'])->name('verify.security');
    });
