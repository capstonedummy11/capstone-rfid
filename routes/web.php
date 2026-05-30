<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ActiveDeviceController;
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
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\SystemSettingsController;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
  $role = strtolower(trim((string) $request->user()?->role));

  if (in_array($role, ['admin', 'instructor'], true)) {
    return redirect()->route('admin.dashboard');
  }
  if ($role === 'clinic') {
    return redirect()->route('clinic.dashboard');
  }
  if ($role === 'console') {
    return redirect()->route('attendanceControlPanel');
  }

  return Inertia::render('LandingPage');
})->name('landingPage');
Route::inertia('/about', 'About')->name('about');
Route::get('/attendance-control-panel/login', [AttendanceController::class, 'panelLogin'])->name('attendanceControlPanel.login');
Route::post('/panel-verify', [AttendanceController::class, 'verifyPanelPin'])->name('panelVerify');
Route::post('/face-recognition/verify-student', [AttendanceController::class, 'verifyStudentFace'])->name('faceRecognition.verifyStudent');

Route::middleware(['auth', 'role:console'])->group(function () {
  Route::get('/attendance-control-panel', [AttendanceController::class, 'controlPanel'])->name('attendanceControlPanel');
  Route::post('/attendance-control-panel/room', [AttendanceController::class, 'selectPanelRoom'])->name('attendanceControlPanel.room');
  Route::post('/attendance-control-panel/logout', [AttendanceController::class, 'panelLogout'])->name('attendanceControlPanel.logout');
  Route::post('/attendance-control-panel/status', [AttendanceController::class, 'panelStatus'])->name('attendanceControlPanel.status');
  Route::post('/attendance-control-panel/rfid-lookup', [AttendanceController::class, 'lookupRfid'])->name('attendanceControlPanel.lookupRfid');
  Route::post('/attendance-control-panel/session-state', [AttendanceController::class, 'updatePanelSessionState'])->name('attendanceControlPanel.sessionState');
  Route::post('/attendance-control-panel/student-face-check', [AttendanceController::class, 'studentFaceCheck'])->name('attendanceControlPanel.studentFaceCheck');
  Route::post('/attendance-control-panel/student-tap', [AttendanceController::class, 'recordStudentTap'])->name('attendanceControlPanel.studentTap');
  Route::post('/attendance-control-panel/attendance-logs', [AttendanceController::class, 'attendanceLogSnapshot'])->name('attendanceControlPanel.attendanceLogs');
  Route::post('/attendance-control-panel/borrow-items-only', [BorrowController::class, 'borrowItemsOnly'])->name('attendanceControlPanel.borrowItemsOnly');
  Route::post('/attendance-control-panel/verify-face', [AttendanceController::class, 'verifyFace'])->name('attendanceControlPanel.verifyFace');
  Route::post('/attendance-control-panel/emergency-alert', [EmergencyController::class, 'storeAlert'])->name('attendanceControlPanel.emergencyAlert');
});

Route::inertia('/register', 'Auth/Register')->name('register_page');

Route::get('/dashboard', function (Request $request) {
  $role = strtolower(trim((string) $request->user()?->role));

  if (in_array($role, ['admin', 'instructor'], true)) {
    return redirect()->route('admin.dashboard');
  }
  if ($role === 'clinic') {
    return redirect()->route('clinic.dashboard');
  }
  if ($role === 'console') {
    return redirect()->route('attendanceControlPanel');
  }

  return redirect()->route('landingPage');
})->middleware('auth')->name('dashboard');

Route::prefix('admin')
  ->middleware(['auth'])
  // ->middleware(['auth'])
  ->name('admin.')
  ->group(function () {
    Route::middleware('role:admin,instructor')->group(function () {
      Route::inertia('/dashboard', 'Auth/Admin/Dashboard', ['title' => 'Dashboard'])->name('dashboard');
      Route::get('/attendance/scanner', [AttendanceController::class, 'scanner'])->name('attendance.scanner');
      Route::get('/attendance/logs', [AttendanceController::class, 'logs'])->name('attendance.logs');
      Route::post('/attendance/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');
      Route::get('/students', [StudentsController::class, 'indexAdmin'], ['title' => 'Instructor Management'])->name('students.index');
      Route::get('/schedules', [ScheduleController::class, 'indexAdmin'])->name('schedules.index');
    });

    Route::middleware('role:admin')->group(function () {
      Route::get('/laboratories', [LaboratoryController::class, 'indexAdmin'])->name('laboratories');
      Route::post('/laboratories', [LaboratoryController::class, 'store'])->name('laboratories.store');
      Route::put('/laboratories/{id}', [LaboratoryController::class, 'update'])->name('laboratories.update');
      Route::delete('/laboratories/{id}', [LaboratoryController::class, 'destroy'])->name('laboratories.destroy');
      Route::get('/borrow', [BorrowController::class, 'index'])->name('borrow');
      Route::get('/rfid', [RfidController::class, 'index'])->name('rfid');
      Route::put('/rfid/{type}/{id}', [RfidController::class, 'update'])->name('rfid.update');
      Route::delete('/rfid/{type}/{id}', [RfidController::class, 'destroy'])->name('rfid.destroy');
      Route::get('/sections', [SectionController::class, 'indexAdmin'])->name('sections.index');
      Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
      Route::put('/sections/{id}', [SectionController::class, 'update'])->name('sections.update');
      Route::delete('/sections/{id}', [SectionController::class, 'destroy'])->name('sections.destroy');
      Route::get('/subjects', [SubjectController::class, 'indexAdmin'])->name('subjects.index');
      Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
      Route::put('/subjects/{id}', [SubjectController::class, 'update'])->name('subjects.update');
      Route::delete('/subjects/{id}', [SubjectController::class, 'destroy'])->name('subjects.destroy');
      Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
      Route::put('/schedules/{id}', [ScheduleController::class, 'update'])->name('schedules.update');
      Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
      Route::get('/inventory', [InventoryController::class, 'indexAdmin'])->name('inventory.index');
      Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
      Route::put('/inventory/{id}', [InventoryController::class, 'update'])->name('inventory.update');
      Route::delete('/inventory/{id}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
      Route::get('/activity-logs', [ActivityLogController::class, 'indexAdmin'])->name('activity-logs.index');
      Route::delete('/activity-logs/{id}', [ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');
      Route::get('/active-devices', [ActiveDeviceController::class, 'index'])->name('active-devices.index');
      Route::put('/active-devices/panel-access', [ActiveDeviceController::class, 'updatePanelAccess'])->name('active-devices.panel-access.update');
      Route::post('/active-devices/{panelSessionId}/force-logout', [ActiveDeviceController::class, 'forceLogout'])->name('active-devices.force-logout');
      Route::get('/settings', [SystemSettingsController::class, 'edit'])->name('settings.edit');
      Route::put('/settings', [SystemSettingsController::class, 'update'])->name('settings.update');
      Route::get('/strands', [StrandController::class, 'indexAdmin'])->name('strands.index');
      Route::post('/strands', [StrandController::class, 'store'])->name('strands.store');
      Route::put('/strands/{id}', [StrandController::class, 'update'])->name('strands.update');
      Route::delete('/strands/{id}', [StrandController::class, 'destroy'])->name('strands.destroy');
      Route::post('/students', [StudentsController::class, 'store'])->name('students.store');
      Route::put('/students/{id}', [StudentsController::class, 'update'])->name('students.update');
      Route::delete('/students/{id}', [StudentsController::class, 'destroy'])->name('students.destroy');
      Route::post('/students/{id}/face-images', [StudentsController::class, 'uploadFaceImage'])->name('students.face-images.upload');
      Route::delete('/students/{id}/face-images/{index}', [StudentsController::class, 'deleteFaceImage'])->name('students.face-images.delete');
      Route::get('/instructors', [InstructorsController::class, 'indexAdmin'])->name('instructors.index');
      Route::post('/instructors', [InstructorsController::class, 'store'])->name('instructors.store');
      Route::put('/instructors/{id}', [InstructorsController::class, 'update'])->name('instructors.update');
      Route::delete('/instructors/{id}', [InstructorsController::class, 'destroy'])->name('instructors.destroy');
      Route::inertia('/students-management', 'StudentsManagement')->name('studentsManagement');
      //Route::inertia('/instructors-management', 'InstructorsManagement', ['title' => 'Instructor Management'])->name('instructorsManagement');
      Route::post('/borrow/return-items', [BorrowController::class, 'returnItems'])->name('borrow.returnItems');
      // Route::inertia('/inventory', 'Auth/Admin/Inventory', ['title' => 'Inventory', 'items' => fn() => \App\Models\Item::all(),])->name('inventory');
      Route::get('/inventory', function () {
        if (! SystemSetting::boolean(SystemSetting::INVENTORY_ENABLED, false)) {
          return redirect()->route('admin.settings.edit')->with('success', 'Inventory is currently disabled.');
        }

        return Inertia::render('Auth/Admin/Inventory', [
          'title' => 'Inventory',
          'items' => \App\Models\Item::all(),
        ]);
      })->name('inventory');
      Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
      Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
      Route::post('/items', [ItemController::class, 'store'])->name('items.store');
    });
  });

Route::prefix('clinic')
  ->middleware(['auth', 'role:clinic,admin'])
  ->name('clinic.')
  ->group(function () {
    Route::get('/dashboard', [ClinicController::class, 'dashboard'])->name('dashboard');
    Route::get('/case-logs', [ClinicController::class, 'caseLogs'])->name('case-logs');
    Route::get('/patient-history', [ClinicController::class, 'patientHistory'])->name('patient-history');
    Route::get('/reports', [ClinicController::class, 'reports'])->name('reports');
    Route::post('/emergency-types', [EmergencyController::class, 'storeType'])->name('emergency-types.store');
    Route::put('/emergency-types/{id}', [EmergencyController::class, 'updateType'])->name('emergency-types.update');
    Route::delete('/emergency-types/{id}', [EmergencyController::class, 'destroyType'])->name('emergency-types.destroy');
    Route::put('/emergency-alerts/{id}', [EmergencyController::class, 'updateAlertStatus'])->name('emergency-alerts.update');
    Route::post('/emergency-alerts/{id}/dispatch', [EmergencyController::class, 'dispatchAlert'])->name('emergency-alerts.dispatch');
  });


//Admin routes

Route::post('/register', [AuthController::class, 'register'])->name('register');
