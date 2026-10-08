<?php

// Routes shared by Admin and verified Instructor accounts under the legacy admin URL.

use App\Http\Controllers\Shared\Attendance\AttendanceController;
use App\Http\Controllers\Shared\Attendance\AttendanceManagementController;
use App\Http\Controllers\Shared\Dashboard\DashboardController;
use App\Http\Controllers\Shared\Messages\MessageController;
use App\Http\Controllers\Shared\OnlineClasses\OnlineClassController;
use App\Http\Controllers\Shared\Schedules\ScheduleController;
use App\Http\Controllers\Shared\Students\StudentsController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['auth', 'role:admin,instructor', 'instructor.verified'])
    ->name('admin.')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

        // Attendance Review
        Route::get('/attendance/scanner', [AttendanceController::class, 'scanner'])->name('attendance.scanner');
        Route::get('/attendance/logs', [AttendanceManagementController::class, 'index'])->name('attendance.logs');
        Route::get('/attendance/subjects/{subject}', [AttendanceManagementController::class, 'dashboard'])->name('attendance.subject');
        Route::get('/attendance/subjects/{subject}/summary', [AttendanceManagementController::class, 'summary'])->name('attendance.summary');
        Route::get('/attendance/subjects/{subject}/students/{student}', [AttendanceManagementController::class, 'student'])->name('attendance.student');
        Route::get('/attendance/subjects/{subject}/sessions/{session}', [AttendanceManagementController::class, 'session'])->name('attendance.session');
        Route::get('/attendance/subjects/{subject}/summary/export/{format}', [AttendanceManagementController::class, 'exportSummary'])->name('attendance.summary.export');
        Route::get('/attendance/subjects/{subject}/sessions/{session}/export/{format}', [AttendanceManagementController::class, 'exportSession'])->name('attendance.session.export');
        Route::get('/attendance/logs/legacy', [AttendanceController::class, 'logs'])->name('attendance.logs.legacy');
        Route::patch('/attendance/logs/status', [AttendanceController::class, 'updateAttendanceStatus'])->name('attendance.logs.status');
        Route::patch('/attendance/online/status', [AttendanceManagementController::class, 'updateOnlineAttendanceStatus'])->name('attendance.online.status');
        Route::post('/attendance/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');

        // Messenger
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages/{message}/reply', [MessageController::class, 'reply'])->name('messages.reply');

        // Online Class Management
        Route::get('/online-classes', [OnlineClassController::class, 'index'])->name('online-classes.index');
        Route::post('/online-classes', [OnlineClassController::class, 'store'])->name('online-classes.store');
        Route::put('/online-classes/{onlineClass}', [OnlineClassController::class, 'update'])->name('online-classes.update');
        Route::put('/online-classes/{onlineClass}/cancel', [OnlineClassController::class, 'cancel'])->name('online-classes.cancel');
        Route::delete('/online-classes/{onlineClass}', [OnlineClassController::class, 'destroy'])->name('online-classes.destroy');

        // Student Management
        Route::get('/students', [StudentsController::class, 'indexAdmin'], ['title' => 'Instructor Management'])->name('students.index');

        // Schedules
        Route::get('/schedules', [ScheduleController::class, 'indexAdmin'])->name('schedules.index');
    });
