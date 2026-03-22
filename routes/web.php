<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BorrowController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'LandingPage')->name('landingPage');
Route::inertia('/about', 'About')->name('about');
Route::inertia('/attendance-control-panel', 'AttendanceControlPanel', [
  'rooms' => ['Computer Lab 1', 'Computer Lab 2', 'RFID Laboratory', 'Network Lab'],
])->name('attendanceControlPanel');

Route::post('/panel-verify', function (\Illuminate\Http\Request $request) {
  $pin = (string) env('PANEL_PIN', '1234');
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
Route::get('/borrow', [BorrowController::class, 'index'])->name('borrow');

Route::prefix('admin')
  //->middleware(['auth', 'role:admin'])
  ->middleware(['auth'])
  ->name('admin.')
  ->group(function () {
    Route::inertia('/dashboard', 'Auth/Admin/Dashboard', ['title' => 'Dashboard'])->name('dashboard');
    Route::inertia('/students-management', 'StudentsManagement')->name('studentsManagement');
    //Route::inertia('/instructors-management', 'InstructorsManagement', ['title' => 'Instructor Management'])->name('instructorsManagement');
    Route::inertia('/laboratories', 'Auth/Admin/Laboratories', ['title' => 'Laboratories'])->name('laboratories');
  });


//Admin routes

Route::post('/register', [AuthController::class, 'register'])->name('register');
