<?php

// Routes shared across roles; keep their existing middleware and permissions.

use App\Http\Controllers\Shared\Attendance\AttendanceController;
use App\Http\Controllers\Shared\Auth\IdleSessionController;
use App\Http\Controllers\Shared\FaceLiveness\FaceLivenessController;
use App\Http\Controllers\Shared\Messages\MessageController;
use App\Http\Controllers\Shared\Reports\ReportController;
use App\Http\Middleware\EnsureParentPortalEnabled;
use Illuminate\Support\Facades\Route;

// Public Messenger
Route::get('/messages/new', [MessageController::class, 'create'])->name('messages.create');
Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
Route::middleware(['auth', 'role:admin,instructor,clinic,registrar,student,parent', EnsureParentPortalEnabled::class])->group(function () {
    // Authenticated Messenger
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/unread-status', [MessageController::class, 'unreadStatus'])->middleware('throttle:realtime-poll')->name('messages.unread-status');
    Route::put('/messages/read-all', [MessageController::class, 'markAllRead'])->name('messages.read-all');
    Route::get('/messages/poll', [MessageController::class, 'pollConversation'])->middleware('throttle:realtime-poll')->name('messages.poll');
    Route::post('/messages/conversation', [MessageController::class, 'sendConversationMessage'])->name('messages.conversation.store');
    Route::post('/messages/{message}/forward-to-parent', [MessageController::class, 'forwardExcuseLetterToParent'])->name('messages.forward-to-parent');

    // Excuse Letter Review
    Route::put('/messages/{message}/excuse-letter-review', [MessageController::class, 'reviewExcuseLetter'])->name('messages.excuse-letters.review');

    // Messenger
    Route::put('/messages/{message}/read', [MessageController::class, 'markRead'])->name('messages.read');
    Route::get('/messages/{message}/attachment', [MessageController::class, 'downloadAttachment'])->name('messages.attachments.show');
});
Route::middleware(['auth', 'role:admin,instructor,clinic,registrar,student,parent', EnsureParentPortalEnabled::class])->group(function () {
    // Reports and Exports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
});

Route::middleware('auth')->group(function () {
    // Authenticated Session
    Route::post('/session/keep-alive', [IdleSessionController::class, 'keepAlive'])->name('session.keep-alive');
    Route::post('/session/idle-logout', [IdleSessionController::class, 'destroy'])->name('session.idle-logout');

    // Face Liveness
    Route::post('/face-liveness/sessions', [FaceLivenessController::class, 'store'])->name('faceLiveness.store');
    Route::post('/face-liveness/sessions/{sessionId}/result', [FaceLivenessController::class, 'show'])->name('faceLiveness.show');
});

// Attendance Evidence
Route::get('/attendance-evidence/{attendanceLog}/{moment}', [AttendanceController::class, 'evidence'])
    ->middleware(['auth', 'role:admin,instructor,student,parent,console', EnsureParentPortalEnabled::class])
    ->name('attendance.evidence');
