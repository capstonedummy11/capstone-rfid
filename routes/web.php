<?php

use App\Http\Controllers\ActiveDeviceController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\InstructorsController;
use App\Http\Controllers\InstructorVerificationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LaboratoryController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OnlineClassController;
use App\Http\Controllers\RegistrarController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RfidController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StaffLoginController;
use App\Http\Controllers\StrandController;
use App\Http\Controllers\StudentParentLoginController;
use App\Http\Controllers\StudentsController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SystemSettingsController;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$staffLoginPath = '/'.(trim(config('fortify.paths.login', 'secure-route'), '/') ?: 'secure-route');

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
    if ($role === 'registrar') {
        return redirect()->route('registrar.dashboard');
    }
    if (in_array($role, ['student', 'parent'], true)) {
        return redirect()->route('student-parent.dashboard');
    }

    return Inertia::render('Auth/StudentParentLogin');
})->name('landingPage');
Route::redirect('/home', '/')->name('home');
Route::inertia('/about', 'About')->name('about');
Route::redirect('/student-parent-login', '/')->name('studentParentLogin');
Route::get('/login', fn () => redirect()->route('landingPage'));
Route::get($staffLoginPath, [StaffLoginController::class, 'create'])
    ->name('staff.login');
if ($staffLoginPath !== '/secure-login') {
    Route::redirect('/secure-login', $staffLoginPath)->name('staff.login.legacy');
}
Route::post('/login', [StudentParentLoginController::class, 'store'])
    ->middleware(array_filter([
        'guest:'.config('fortify.guard'),
        config('fortify.limiters.login') ? 'throttle:'.config('fortify.limiters.login') : null,
    ]))
    ->name('student-parent.login.store');
Route::post($staffLoginPath, [StaffLoginController::class, 'store'])
    ->middleware(array_filter([
        'guest:'.config('fortify.guard'),
        config('fortify.limiters.login') ? 'throttle:'.config('fortify.limiters.login') : null,
    ]))
    ->name('staff.login.store');
Route::get('/messages/new', [MessageController::class, 'create'])->name('messages.create');
Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
Route::middleware(['auth', 'role:admin,instructor,clinic,registrar,student,parent'])->group(function () {
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/unread-status', [MessageController::class, 'unreadStatus'])->name('messages.unread-status');
    Route::post('/messages/conversation', [MessageController::class, 'sendConversationMessage'])->name('messages.conversation.store');
    Route::put('/messages/{message}/read', [MessageController::class, 'markRead'])->name('messages.read');
    Route::get('/messages/{message}/attachment', [MessageController::class, 'downloadAttachment'])->name('messages.attachments.show');
});
Route::middleware(['auth', 'role:admin,instructor,clinic,registrar,student,parent'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
});
Route::get('/attendance-control-panel/login', [AttendanceController::class, 'panelLogin'])->name('attendanceControlPanel.login');
Route::post('/panel-verify', [AttendanceController::class, 'verifyPanelPin'])->name('panelVerify');
Route::post('/face-recognition/verify-student', [AttendanceController::class, 'verifyStudentFace'])->name('faceRecognition.verifyStudent');
Route::get('/attendance-evidence/{attendanceLog}/{moment}', [AttendanceController::class, 'evidence'])
    ->middleware(['auth', 'role:admin,instructor,student,parent'])
    ->name('attendance.evidence');

Route::middleware(['auth', 'role:console'])->group(function () {
    Route::get('/attendance-control-panel', [AttendanceController::class, 'controlPanel'])->name('attendanceControlPanel');
    Route::post('/attendance-control-panel/room', [AttendanceController::class, 'selectPanelRoom'])->name('attendanceControlPanel.room');
    Route::post('/attendance-control-panel/logout', [AttendanceController::class, 'panelLogout'])->name('attendanceControlPanel.logout');
    Route::post('/attendance-control-panel/status', [AttendanceController::class, 'panelStatus'])->name('attendanceControlPanel.status');
    Route::post('/attendance-control-panel/rfid-lookup', [AttendanceController::class, 'lookupRfid'])->name('attendanceControlPanel.lookupRfid');
    Route::post('/attendance-control-panel/session-state', [AttendanceController::class, 'updatePanelSessionState'])->name('attendanceControlPanel.sessionState');
    Route::post('/attendance-control-panel/student-face-check', [AttendanceController::class, 'studentFaceCheck'])->name('attendanceControlPanel.studentFaceCheck');
    Route::post('/attendance-control-panel/instructor-face-check', [AttendanceController::class, 'instructorFaceCheck'])->name('attendanceControlPanel.instructorFaceCheck');
    Route::post('/attendance-control-panel/student-tap', [AttendanceController::class, 'recordStudentTap'])->name('attendanceControlPanel.studentTap');
    Route::post('/attendance-control-panel/attendance-logs', [AttendanceController::class, 'attendanceLogSnapshot'])->name('attendanceControlPanel.attendanceLogs');
    Route::post('/attendance-control-panel/borrow-items-only', [BorrowController::class, 'borrowItemsOnly'])->name('attendanceControlPanel.borrowItemsOnly');
    Route::post('/attendance-control-panel/verify-face', [AttendanceController::class, 'verifyFace'])->name('attendanceControlPanel.verifyFace');
    Route::post('/attendance-control-panel/emergency-alert', [EmergencyController::class, 'storeAlert'])->name('attendanceControlPanel.emergencyAlert');
});

Route::inertia('/register', 'Auth/Register')->name('register');

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
    if ($role === 'registrar') {
        return redirect()->route('registrar.dashboard');
    }
    if (in_array($role, ['student', 'parent'], true)) {
        return redirect()->route('student-parent.dashboard');
    }

    return redirect()->route('landingPage');
})->middleware('auth')->name('dashboard');

Route::prefix('instructor')
    ->middleware(['auth', 'role:instructor'])
    ->name('instructor.')
    ->group(function () {
        Route::get('/verify', [InstructorVerificationController::class, 'show'])->name('verify');
        Route::post('/verify/face', [InstructorVerificationController::class, 'verifyFace'])->name('verify.face');
        Route::post('/verify/otp/send', [InstructorVerificationController::class, 'sendOtp'])->name('verify.otp.send');
        Route::post('/verify/otp', [InstructorVerificationController::class, 'verifyOtp'])->name('verify.otp');
        Route::post('/verify/security/setup', [InstructorVerificationController::class, 'setupSecurity'])->name('verify.security.setup');
        Route::post('/verify/security', [InstructorVerificationController::class, 'verifySecurity'])->name('verify.security');
    });

Route::prefix('registrar')
    ->middleware(['auth', 'role:registrar'])
    ->name('registrar.')
    ->group(function () {
        Route::get('/dashboard', [RegistrarController::class, 'dashboard'])->name('dashboard');
        Route::get('/biometric-enrollment', [RegistrarController::class, 'biometricEnrollment'])->name('biometric-enrollment');
        Route::get('/instructor-face-enrollment', [RegistrarController::class, 'instructorFaceEnrollment'])->name('instructor-face-enrollment');
        Route::put('/students/{student}/rfid', [RegistrarController::class, 'updateStudentRfid'])->name('students.rfid');
        Route::post('/students/{student}/face', [RegistrarController::class, 'uploadStudentFace'])->name('students.face');
        Route::delete('/students/{student}/face/{index}', [RegistrarController::class, 'deleteStudentFace'])->name('students.face.delete');
        Route::put('/faculty/{user}/rfid', [RegistrarController::class, 'updateFacultyRfid'])->name('faculty.rfid');
        Route::post('/faculty/{user}/face', [RegistrarController::class, 'uploadFacultyFace'])->name('faculty.face');
        Route::delete('/faculty/{user}/face/{index}', [RegistrarController::class, 'deleteFacultyFace'])->name('faculty.face.delete');
    });

Route::prefix('admin')
    ->middleware(['auth'])
  // ->middleware(['auth'])
    ->name('admin.')
    ->group(function () {
        Route::middleware(['role:admin,instructor', 'instructor.verified'])->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');
            Route::get('/attendance/scanner', [AttendanceController::class, 'scanner'])->name('attendance.scanner');
            Route::get('/attendance/logs', [AttendanceController::class, 'logs'])->name('attendance.logs');
            Route::patch('/attendance/logs/status', [AttendanceController::class, 'updateAttendanceStatus'])->name('attendance.logs.status');
            Route::post('/attendance/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');
            Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
            Route::post('/messages/{message}/reply', [MessageController::class, 'reply'])->name('messages.reply');
            Route::get('/online-classes', [OnlineClassController::class, 'index'])->name('online-classes.index');
            Route::post('/online-classes', [OnlineClassController::class, 'store'])->name('online-classes.store');
            Route::put('/online-classes/{onlineClass}', [OnlineClassController::class, 'update'])->name('online-classes.update');
            Route::put('/online-classes/{onlineClass}/cancel', [OnlineClassController::class, 'cancel'])->name('online-classes.cancel');
            Route::delete('/online-classes/{onlineClass}', [OnlineClassController::class, 'destroy'])->name('online-classes.destroy');
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
            Route::get('/activity-logs/export', [ActivityLogController::class, 'export'])->name('activity-logs.export');
            Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
            Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
            Route::put('/users/{id}', [AdminUserController::class, 'update'])->name('users.update');
            Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('users.destroy');
            Route::get('/online-class-logs', [OnlineClassController::class, 'logs'])->name('online-class-logs.index');
            Route::get('/online-class-logs/export', [OnlineClassController::class, 'exportLogs'])->name('online-class-logs.export');
            Route::get('/active-devices', [ActiveDeviceController::class, 'index'])->name('active-devices.index');
            Route::put('/active-devices/panel-access', [ActiveDeviceController::class, 'updatePanelAccess'])->name('active-devices.panel-access.update');
            Route::put('/active-devices/{panelSessionId}/pin', [ActiveDeviceController::class, 'updatePanelDevicePin'])->name('active-devices.pin.update');
            Route::post('/active-devices/{panelSessionId}/force-logout', [ActiveDeviceController::class, 'forceLogout'])->name('active-devices.force-logout');
            Route::get('/settings', [SystemSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [SystemSettingsController::class, 'update'])->name('settings.update');
            Route::post('/settings/emergency-sounds', [SystemSettingsController::class, 'storeEmergencySound'])->name('settings.emergency-sounds.store');
            Route::put('/settings/emergency-sounds/{id}/select', [SystemSettingsController::class, 'selectEmergencySound'])->name('settings.emergency-sounds.select');
            Route::delete('/settings/emergency-sounds/{id}', [SystemSettingsController::class, 'destroyEmergencySound'])->name('settings.emergency-sounds.destroy');
            Route::get('/strands', [StrandController::class, 'indexAdmin'])->name('strands.index');
            Route::post('/strands', [StrandController::class, 'store'])->name('strands.store');
            Route::put('/strands/{id}', [StrandController::class, 'update'])->name('strands.update');
            Route::delete('/strands/{id}', [StrandController::class, 'destroy'])->name('strands.destroy');
            Route::post('/students', [StudentsController::class, 'store'])->name('students.store');
            Route::put('/students/{id}', [StudentsController::class, 'update'])->name('students.update');
            Route::put('/students/{id}/password/reset-default', [StudentsController::class, 'resetStudentAccountPassword'])
                ->name('students.password.reset-default');
            Route::delete('/students/{id}', [StudentsController::class, 'destroy'])->name('students.destroy');
            Route::post('/students/{id}/parents', [StudentsController::class, 'storeParent'])->name('students.parents.store');
            Route::put('/students/{id}/parents/{parent}', [StudentsController::class, 'updateParent'])->name('students.parents.update');
            Route::delete('/students/{id}/parents/{parent}', [StudentsController::class, 'destroyParent'])->name('students.parents.destroy');
            Route::get('/instructors', [InstructorsController::class, 'indexAdmin'])->name('instructors.index');
            Route::post('/instructors', [InstructorsController::class, 'store'])->name('instructors.store');
            Route::put('/instructors/{id}', [InstructorsController::class, 'update'])->name('instructors.update');
            Route::delete('/instructors/{id}', [InstructorsController::class, 'destroy'])->name('instructors.destroy');
            Route::inertia('/students-management', 'StudentsManagement')->name('studentsManagement');
            // Route::inertia('/instructors-management', 'InstructorsManagement', ['title' => 'Instructor Management'])->name('instructorsManagement');
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
        Route::get('/emergency-sounds/{id}', [SystemSettingsController::class, 'showEmergencySound'])->name('emergency-sounds.show');
        Route::get('/dashboard', [ClinicController::class, 'dashboard'])->name('dashboard');
        Route::get('/case-logs', [ClinicController::class, 'caseLogs'])->name('case-logs');
        Route::post('/case-logs', [ClinicController::class, 'storeCase'])->name('case-logs.store');
        Route::put('/case-logs/{id}', [ClinicController::class, 'updateCase'])->name('case-logs.update');
        Route::post('/case-logs/{id}/history', [ClinicController::class, 'createHistoryFromCase'])->name('case-logs.history');
        Route::get('/patient-history', [ClinicController::class, 'patientHistory'])->name('patient-history');
        Route::post('/patient-history', [ClinicController::class, 'storeHistory'])->name('patient-history.store');
        Route::put('/patient-history/{id}', [ClinicController::class, 'updateHistory'])->name('patient-history.update');
        Route::delete('/patient-history/{id}', [ClinicController::class, 'destroyHistory'])->name('patient-history.destroy');
        Route::get('/reports', [ClinicController::class, 'reports'])->name('reports');
        Route::get('/reports/export', [ClinicController::class, 'exportReports'])->name('reports.export');
        Route::get('/emergency-hotlines', [EmergencyController::class, 'hotlines'])->name('emergency-hotlines.index');
        Route::post('/emergency-hotlines', [EmergencyController::class, 'storeHotline'])->name('emergency-hotlines.store');
        Route::put('/emergency-hotlines/{id}', [EmergencyController::class, 'updateHotline'])->name('emergency-hotlines.update');
        Route::delete('/emergency-hotlines/{id}', [EmergencyController::class, 'destroyHotline'])->name('emergency-hotlines.destroy');
        Route::post('/emergency-types', [EmergencyController::class, 'storeType'])->name('emergency-types.store');
        Route::put('/emergency-types/{id}', [EmergencyController::class, 'updateType'])->name('emergency-types.update');
        Route::delete('/emergency-types/{id}', [EmergencyController::class, 'destroyType'])->name('emergency-types.destroy');
        Route::put('/emergency-alerts/{id}', [EmergencyController::class, 'updateAlertStatus'])->name('emergency-alerts.update');
        Route::post('/emergency-alerts/{id}/dispatch', [EmergencyController::class, 'dispatchAlert'])->name('emergency-alerts.dispatch');
    });

Route::prefix('student-parent')
    ->middleware(['auth', 'role:student,parent'])
    ->name('student-parent.')
    ->group(function () {
        Route::get('/dashboard', [StudentsController::class, 'portalDashboard'])->name('dashboard');
        Route::get('/profile', [StudentsController::class, 'portalProfile'])->name('profile.show');
        Route::put('/profile', [StudentsController::class, 'updatePortalProfile'])->name('profile.update');
        Route::put('/password', [StudentsController::class, 'updatePortalPassword'])->name('password.update');
        Route::get('/attendance', [StudentsController::class, 'portalAttendance'])->name('attendance');
        Route::get('/excuse-letters', [StudentsController::class, 'portalExcuseLetters'])->name('excuse-letters.index');
        Route::post('/excuse-letters', [StudentsController::class, 'storePortalExcuseLetter'])->name('excuse-letters.store');
        Route::put('/excuse-letters/{letter}/approve', [StudentsController::class, 'approvePortalExcuseLetter'])->name('excuse-letters.approve');
        Route::get('/excuse-letters/{letter}/download', [StudentsController::class, 'downloadPortalExcuseLetter'])->name('excuse-letters.download');
        Route::get('/excuse-letters/{letter}/attachment', [StudentsController::class, 'downloadPortalExcuseLetterAttachment'])->name('excuse-letters.attachment');
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages', [MessageController::class, 'sendConversationMessage'])->name('messages.store');
        Route::get('/notifications', [StudentsController::class, 'portalNotifications'])->name('notifications.index');
        Route::put('/notifications/{notification}/read', [StudentsController::class, 'markPortalNotificationRead'])->name('notifications.read');
        Route::get('/online-classes', [OnlineClassController::class, 'studentIndex'])->name('online-classes.index');
        Route::post('/online-classes/{onlineClass}/join', [OnlineClassController::class, 'join'])->name('online-classes.join');
    });

// Admin routes

Route::post('/register', [AuthController::class, 'register'])->name('register.store');
