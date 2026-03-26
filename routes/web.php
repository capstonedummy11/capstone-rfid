<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\RfidController;
use App\Http\Controllers\StudentsController;
use App\Http\Controllers\InstructorsController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\LaboratoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
    Route::get('/laboratories', [LaboratoryController::class, 'indexAdmin'])->name('laboratories');
    Route::post('/laboratories', [LaboratoryController::class, 'store'])->name('laboratories.store');
    Route::put('/laboratories/{id}', [LaboratoryController::class, 'update'])->name('laboratories.update');
    Route::delete('/laboratories/{id}', [LaboratoryController::class, 'destroy'])->name('laboratories.destroy');
    Route::get('/borrow', [BorrowController::class, 'index'])->name('borrow');
    Route::get('/rfid', [RfidController::class, 'index'])->name('rfid');
    Route::put('/rfid/{type}/{id}', [RfidController::class, 'update'])->name('rfid.update');
    Route::delete('/rfid/{type}/{id}', [RfidController::class, 'destroy'])->name('rfid.destroy');
    Route::get('/students', [StudentsController::class, 'indexAdmin'])->name('students.index');
    Route::post('/students', [StudentsController::class, 'store'])->name('students.store');
    Route::put('/students/{id}', [StudentsController::class, 'update'])->name('students.update');
    Route::delete('/students/{id}', [StudentsController::class, 'destroy'])->name('students.destroy');
    Route::get('/sections', [SectionController::class, 'indexAdmin'])->name('sections.index');
    Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
    Route::put('/sections/{id}', [SectionController::class, 'update'])->name('sections.update');
    Route::delete('/sections/{id}', [SectionController::class, 'destroy'])->name('sections.destroy');
    Route::get('/courses', [CourseController::class, 'indexAdmin'])->name('courses.index');
    Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::put('/courses/{id}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('/courses/{id}', [CourseController::class, 'destroy'])->name('courses.destroy');
    Route::get('/instructors', [InstructorsController::class, 'indexAdmin'])->name('instructors.index');
    Route::post('/instructors', [InstructorsController::class, 'store'])->name('instructors.store');
    Route::put('/instructors/{id}', [InstructorsController::class, 'update'])->name('instructors.update');
    Route::delete('/instructors/{id}', [InstructorsController::class, 'destroy'])->name('instructors.destroy');
  });


//Admin routes

Route::post('/register', [AuthController::class, 'register'])->name('register');
