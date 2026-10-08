<?php

// Clinic routes also available to Admin as before.

use App\Http\Controllers\Clinic\Records\ClinicController;
use App\Http\Controllers\Shared\Emergency\EmergencyController;
use App\Http\Controllers\Shared\SystemSettings\SystemSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('clinic')
    ->middleware(['auth', 'role:clinic,admin'])
    ->name('clinic.')
    ->group(function () {
        // Emergency Sounds
        Route::get('/emergency-sounds/{id}', [SystemSettingsController::class, 'showEmergencySound'])->name('emergency-sounds.show');

        // Dashboard
        Route::get('/dashboard', [ClinicController::class, 'dashboard'])->name('dashboard');

        // Case Logs
        Route::get('/case-logs', [ClinicController::class, 'caseLogs'])->name('case-logs');
        Route::post('/case-logs', [ClinicController::class, 'storeCase'])->name('case-logs.store');
        Route::put('/case-logs/{id}', [ClinicController::class, 'updateCase'])->name('case-logs.update');
        Route::post('/case-logs/{id}/history', [ClinicController::class, 'createHistoryFromCase'])->name('case-logs.history');

        // Patient History
        Route::get('/patient-history', [ClinicController::class, 'patientHistory'])->name('patient-history');
        Route::post('/patient-history', [ClinicController::class, 'storeHistory'])->name('patient-history.store');
        Route::put('/patient-history/{id}', [ClinicController::class, 'updateHistory'])->name('patient-history.update');
        Route::delete('/patient-history/{id}', [ClinicController::class, 'destroyHistory'])->name('patient-history.destroy');

        // Clinic Reports
        Route::get('/reports', [ClinicController::class, 'reports'])->name('reports');
        Route::get('/reports/export', [ClinicController::class, 'exportReports'])->name('reports.export');

        // Emergency Hotlines
        Route::get('/emergency-hotlines', [EmergencyController::class, 'hotlines'])->name('emergency-hotlines.index');
        Route::post('/emergency-hotlines', [EmergencyController::class, 'storeHotline'])->name('emergency-hotlines.store');
        Route::put('/emergency-hotlines/{id}', [EmergencyController::class, 'updateHotline'])->name('emergency-hotlines.update');
        Route::delete('/emergency-hotlines/{id}', [EmergencyController::class, 'destroyHotline'])->name('emergency-hotlines.destroy');

        // Emergency Types
        Route::post('/emergency-types', [EmergencyController::class, 'storeType'])->name('emergency-types.store');
        Route::put('/emergency-types/{id}', [EmergencyController::class, 'updateType'])->name('emergency-types.update');
        Route::delete('/emergency-types/{id}', [EmergencyController::class, 'destroyType'])->name('emergency-types.destroy');

        // Emergency Dispatch
        Route::put('/emergency-alerts/{id}', [EmergencyController::class, 'updateAlertStatus'])->name('emergency-alerts.update');
        Route::post('/emergency-alerts/{id}/dispatch', [EmergencyController::class, 'dispatchAlert'])->name('emergency-alerts.dispatch');
    });
