<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\RfidController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StudentsController;
use App\Http\Controllers\InstructorsController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StrandController;
use App\Http\Controllers\LaboratoryController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\ItemController;
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

Route::inertia('/register', 'Auth/Register')->name('register_page');

Route::get('/dashboard', function (Request $request) {
  $role = strtolower(trim((string) $request->user()?->role));

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
    Route::get('/attendance/scanner', [AttendanceController::class, 'scanner'])->name('attendance.scanner');
    Route::get('/attendance/logs', [AttendanceController::class, 'logs'])->name('attendance.logs');
    Route::post('/attendance/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');
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
    Route::get('/subjects', [SubjectController::class, 'indexAdmin'])->name('subjects.index');
    Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::put('/subjects/{id}', [SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('/subjects/{id}', [SubjectController::class, 'destroy'])->name('subjects.destroy');
    Route::get('/schedules', [ScheduleController::class, 'indexAdmin'])->name('schedules.index');
    Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
    Route::put('/schedules/{id}', [ScheduleController::class, 'update'])->name('schedules.update');
    Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
    Route::get('/inventory', [InventoryController::class, 'indexAdmin'])->name('inventory.index');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::put('/inventory/{id}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{id}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    Route::get('/activity-logs', [ActivityLogController::class, 'indexAdmin'])->name('activity-logs.index');
    Route::delete('/activity-logs/{id}', [ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');
    Route::get('/strands', [StrandController::class, 'indexAdmin'])->name('strands.index');
    Route::post('/strands', [StrandController::class, 'store'])->name('strands.store');
    Route::put('/strands/{id}', [StrandController::class, 'update'])->name('strands.update');
    Route::delete('/strands/{id}', [StrandController::class, 'destroy'])->name('strands.destroy');
    Route::get('/instructors', [InstructorsController::class, 'indexAdmin'])->name('instructors.index');
    Route::post('/instructors', [InstructorsController::class, 'store'])->name('instructors.store');
    Route::put('/instructors/{id}', [InstructorsController::class, 'update'])->name('instructors.update');
    Route::delete('/instructors/{id}', [InstructorsController::class, 'destroy'])->name('instructors.destroy');
    Route::inertia('/students-management', 'StudentsManagement')->name('studentsManagement');
    //Route::inertia('/instructors-management', 'InstructorsManagement', ['title' => 'Instructor Management'])->name('instructorsManagement');
    Route::post('/borrow/return-items', [BorrowController::class, 'returnItems'])->name('borrow.returnItems');
    Route::inertia('/inventory', 'Auth/Admin/Inventory', ['title' => 'Inventory', 'items' => fn () => \App\Models\Item::all(),])->name('inventory');
    Route::post('/items', [ItemController::class, 'store'])->name('items.store');
  });


//Admin routes

Route::post('/register', [AuthController::class, 'register'])->name('register');
