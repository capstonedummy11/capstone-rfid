<?php

// Routes shared across roles; keep their existing middleware and permissions.

use App\Http\Controllers\Shared\Attendance\AttendanceController;
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
    Route::get('/messages/unread-status', [MessageController::class, 'unreadStatus'])->name('messages.unread-status');
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
    // Face Liveness
    Route::post('/face-liveness/sessions', [FaceLivenessController::class, 'store'])->name('faceLiveness.store');
    Route::post('/face-liveness/sessions/{sessionId}/result', [FaceLivenessController::class, 'show'])->name('faceLiveness.show');
});

// Attendance Evidence
Route::get('/attendance-evidence/{attendanceLog}/{moment}', [AttendanceController::class, 'evidence'])
    ->middleware(['auth', 'role:admin,instructor,student,parent', EnsureParentPortalEnabled::class])
    ->name('attendance.evidence');
