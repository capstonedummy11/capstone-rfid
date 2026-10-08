<?php

// Student and Parent portal routes.

use App\Http\Controllers\Shared\Messages\MessageController;
use App\Http\Controllers\Shared\OnlineClasses\OnlineClassController;
use App\Http\Controllers\Shared\Students\StudentsController;
use App\Http\Middleware\EnsureParentPortalEnabled;
use Illuminate\Support\Facades\Route;

Route::prefix('student-parent')
    ->middleware(['auth', 'role:student,parent', EnsureParentPortalEnabled::class])
    ->name('student-parent.')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', [StudentsController::class, 'portalDashboard'])->name('dashboard');

        // Profile and Password
        Route::get('/profile', [StudentsController::class, 'portalProfile'])->name('profile.show');
        Route::put('/profile', [StudentsController::class, 'updatePortalProfile'])->name('profile.update');
        Route::put('/password', [StudentsController::class, 'updatePortalPassword'])->name('password.update');

        // Attendance History
        Route::get('/attendance', [StudentsController::class, 'portalAttendance'])->name('attendance');

        // Excuse Letters
        Route::get('/excuse-letters', [StudentsController::class, 'portalExcuseLetters'])->name('excuse-letters.index');
        Route::post('/excuse-letters', [StudentsController::class, 'storePortalExcuseLetter'])->name('excuse-letters.store');
        Route::put('/excuse-letters/{letter}/approve', [StudentsController::class, 'approvePortalExcuseLetter'])->name('excuse-letters.approve');
        Route::get('/excuse-letters/{letter}/download', [StudentsController::class, 'downloadPortalExcuseLetter'])->name('excuse-letters.download');
        Route::get('/excuse-letters/{letter}/attachment', [StudentsController::class, 'downloadPortalExcuseLetterAttachment'])->name('excuse-letters.attachment');

        // Messenger
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages', [MessageController::class, 'sendConversationMessage'])->name('messages.store');

        // Notifications
        Route::get('/notifications', [StudentsController::class, 'portalNotifications'])->name('notifications.index');
        Route::put('/notifications/{notification}/read', [StudentsController::class, 'markPortalNotificationRead'])->name('notifications.read');

        // Online Classes
        Route::get('/online-classes', [OnlineClassController::class, 'studentIndex'])->name('online-classes.index');
        Route::post('/online-classes/{onlineClass}/join', [OnlineClassController::class, 'join'])->name('online-classes.join');
    });
