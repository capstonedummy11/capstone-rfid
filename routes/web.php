<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BorrowController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function (Request $request) {
  $role = strtolower(trim((string) $request->user()?->role));

  if ($role === 'admin') {
    return redirect()->route('admin.dashboard');
  }

  return Inertia::render('LandingPage');
})->name('landingPage');
Route::inertia('/about', 'About')->name('about');
Route::get('/attendance-control-panel', [AttendanceController::class, 'controlPanel'])->name('attendanceControlPanel');
Route::post('/attendance-control-panel/rfid-lookup', [AttendanceController::class, 'lookupRfid'])->name('attendanceControlPanel.lookupRfid');
Route::post('/attendance-control-panel/session-state', [AttendanceController::class, 'updatePanelSessionState'])->name('attendanceControlPanel.sessionState');
Route::post('/attendance-control-panel/student-tap', [AttendanceController::class, 'recordStudentTap'])->name('attendanceControlPanel.studentTap');
Route::post('/attendance-control-panel/attendance-logs', [AttendanceController::class, 'attendanceLogSnapshot'])->name('attendanceControlPanel.attendanceLogs');

Route::post('/panel-verify', function (\Illuminate\Http\Request $request) {
  $pin = (string) config('panel.pin', '1234');
  if ((string) $request->input('pin', '') === $pin) {
    return response()->json(['success' => true]);
  }
  return response()->json(['success' => false, 'message' => 'Incorrect PIN. Please try again.'], 401);
})->name('panelVerify');
Route::inertia('/attendance-scanner', 'AttendanceScanner')->name('attendanceScanner');
Route::inertia('/rfid-registration', 'RFIDRegistration')->name('rfidRegistration');
Route::inertia('/classes', 'Classes')->name('classes');
Route::inertia('/attendance-logs', 'AttendanceLogs')->name('attendanceLogs');
Route::inertia('/register', 'Auth/Register')->name('register_page');

Route::get('/dashboard', function () {
  $role = strtolower(trim((string) auth()->user()?->role));

  if ($role === 'admin') {
    return redirect()->route('admin.dashboard');
  }

  return redirect()->route('landingPage');
})->middleware('auth')->name('dashboard');

Route::prefix('admin')
  ->middleware(['auth', 'role:admin'])
  // ->middleware(['auth'])
  ->name('admin.')
  ->group(function () {
    Route::inertia('/dashboard', 'Auth/Admin/Dashboard', ['title' => 'Dashboard'])->name('dashboard');
    Route::inertia('/students-management', 'StudentsManagement')->name('studentsManagement');
    //Route::inertia('/instructors-management', 'InstructorsManagement', ['title' => 'Instructor Management'])->name('instructorsManagement');
    Route::inertia('/laboratories', 'Auth/Admin/Laboratories', ['title' => 'Laboratories'])->name('laboratories');
    Route::get('/borrow', [BorrowController::class, 'index'])->name('borrow');
    Route::post('/borrow/return-items', [BorrowController::class, 'returnItems'])->name('borrow.returnItems');
    Route::inertia('/inventory', 'Auth/Admin/Inventory', ['title' => 'Inventory'])->name('inventory');
  });


//Admin routes

Route::post('/register', [AuthController::class, 'register'])->name('register');
