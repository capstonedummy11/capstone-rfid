<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\ItemController;
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
Route::inertia('/attendance-control-panel', 'AttendanceControlPanel', [
  'rooms' => ['Computer Lab 1', 'Computer Lab 2', 'RFID Laboratory', 'Network Lab'],
  'studentToastSeconds' => config('panel.student_toast_seconds', 15),
  'studentInfoVisibleSeconds' => config('panel.student_info_visible_seconds', 10),
])->name('attendanceControlPanel');

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
    Route::get('/borrow', [BorrowController::class, 'index'], ['title' => 'Borrowing'])->name('borrow');
    Route::inertia('/inventory', 'Auth/Admin/Inventory', ['title' => 'Inventory', 'items' => \App\Models\Item::all(),])->name('inventory');
    Route::post('/items', [ItemController::class, 'store'])->name('items.store');
  });


//Admin routes

Route::post('/register', [AuthController::class, 'register'])->name('register');
