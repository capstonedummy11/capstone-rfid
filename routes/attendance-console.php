<?php

// Attendance console routes, including its login and verification endpoints.

use App\Http\Controllers\Shared\Attendance\AttendanceController;
use App\Http\Controllers\Shared\Borrowing\BorrowController;
use App\Http\Controllers\Shared\Emergency\EmergencyController;
use Illuminate\Support\Facades\Route;

// Console Login
Route::get('/attendance-control-panel/login', [AttendanceController::class, 'panelLogin'])->name('attendanceControlPanel.login');
Route::post('/panel-verify', [AttendanceController::class, 'verifyPanelPin'])->name('panelVerify');

// Legacy Face Verification
Route::post('/face-recognition/verify-student', [AttendanceController::class, 'verifyStudentFace'])->name('faceRecognition.verifyStudent');

Route::middleware(['auth', 'role:console'])->group(function () {
    // Attendance Console
    Route::get('/attendance-control-panel', [AttendanceController::class, 'controlPanel'])->name('attendanceControlPanel');
    Route::post('/attendance-control-panel/room', [AttendanceController::class, 'selectPanelRoom'])->name('attendanceControlPanel.room');
    Route::post('/attendance-control-panel/logout', [AttendanceController::class, 'panelLogout'])->name('attendanceControlPanel.logout');
    Route::post('/attendance-control-panel/status', [AttendanceController::class, 'panelStatus'])->name('attendanceControlPanel.status');
    Route::post('/attendance-control-panel/rfid-lookup', [AttendanceController::class, 'lookupRfid'])->name('attendanceControlPanel.lookupRfid');
    Route::post('/attendance-control-panel/session-state', [AttendanceController::class, 'updatePanelSessionState'])->name('attendanceControlPanel.sessionState');

    // Face Verification
    Route::post('/attendance-control-panel/student-face-check', [AttendanceController::class, 'studentFaceCheck'])->name('attendanceControlPanel.studentFaceCheck');
    Route::post('/attendance-control-panel/instructor-face-check', [AttendanceController::class, 'instructorFaceCheck'])->name('attendanceControlPanel.instructorFaceCheck');

    // Attendance Console
    Route::post('/attendance-control-panel/student-tap', [AttendanceController::class, 'recordStudentTap'])->name('attendanceControlPanel.studentTap');
    Route::post('/attendance-control-panel/attendance-logs', [AttendanceController::class, 'attendanceLogSnapshot'])->name('attendanceControlPanel.attendanceLogs');

    // Console Borrowing
    Route::post('/attendance-control-panel/borrow-items-only', [BorrowController::class, 'borrowItemsOnly'])->name('attendanceControlPanel.borrowItemsOnly');

    // Face Verification
    Route::post('/attendance-control-panel/verify-face', [AttendanceController::class, 'verifyFace'])->name('attendanceControlPanel.verifyFace');

    // Emergency Alerts
    Route::post('/attendance-control-panel/emergency-alert', [EmergencyController::class, 'storeAlert'])->name('attendanceControlPanel.emergencyAlert');
});
