<?php

// Registrar-only routes.

use App\Http\Controllers\Registrar\Enrollment\RegistrarController;
use Illuminate\Support\Facades\Route;

Route::prefix('registrar')
    ->middleware(['auth', 'role:registrar'])
    ->name('registrar.')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', [RegistrarController::class, 'dashboard'])->name('dashboard');

        // Student Biometric Enrollment
        Route::get('/biometric-enrollment', [RegistrarController::class, 'biometricEnrollment'])->name('biometric-enrollment');

        // Instructor Biometric Enrollment
        Route::get('/instructor-face-enrollment', [RegistrarController::class, 'instructorFaceEnrollment'])->name('instructor-face-enrollment');

        // Student RFID Enrollment
        Route::put('/students/{student}/rfid', [RegistrarController::class, 'updateStudentRfid'])->name('students.rfid');

        // Student Face Enrollment
        Route::post('/students/{student}/face', [RegistrarController::class, 'uploadStudentFace'])->name('students.face');
        Route::delete('/students/{student}/face/{index}', [RegistrarController::class, 'deleteStudentFace'])->name('students.face.delete');

        // Instructor Biometric Enrollment
        Route::put('/faculty/{user}/rfid', [RegistrarController::class, 'updateFacultyRfid'])->name('faculty.rfid');
        Route::post('/faculty/{user}/face', [RegistrarController::class, 'uploadFacultyFace'])->name('faculty.face');
        Route::delete('/faculty/{user}/face/{index}', [RegistrarController::class, 'deleteFacultyFace'])->name('faculty.face.delete');
    });
