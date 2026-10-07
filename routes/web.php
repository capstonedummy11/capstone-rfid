<?php
// File purpose: Web routes para sa public, staff, Console, Clinic, at Student/Parent flows.

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\ActiveDeviceController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminLoginVerificationController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\FaceLivenessController;
use App\Http\Controllers\FirstLoginPasswordController;
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
use App\Http\Controllers\RootOverrideController;
use App\Http\Controllers\RootOwnershipController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StaffLoginController;
use App\Http\Controllers\StrandController;
use App\Http\Controllers\StudentParentLoginController;
use App\Http\Controllers\StudentsController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Middleware\EnsureParentPortalEnabled;
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
    if ($role === 'student' || ($role === 'parent' && SystemSetting::boolean(SystemSetting::PARENT_PORTAL_ENABLED, false))) {
        return redirect()->route('student-parent.dashboard');
    }

    return Inertia::render('Auth/StudentParentLogin');
})->name('landingPage');
Route::redirect('/home', '/')->name('home');
Route::inertia('/about', 'About')->name('about');
Route::middleware(['auth', 'throttle:root-ownership'])->group(function () {
    Route::get('/root-ownership/transfers/{transfer}/accept', [RootOwnershipController::class, 'acceptShow'])->name('root-ownership.accept.show');
    // FEATURE:root-ownership - route para sa Ownership Transfer and Override.
    Route::post('/root-ownership/transfers/{transfer}/accept', [RootOwnershipController::class, 'accept'])->middleware('signed')->name('root-ownership.accept.store');
});
Route::middleware('throttle:root-ownership')->group(function () {
    Route::get('/root-ownership/transfers/{transfer}/cancel', [RootOwnershipController::class, 'cancelShow'])->name('root-ownership.cancel.show');
    // FEATURE:root-ownership - route para sa Ownership Transfer and Override.
    Route::post('/root-ownership/transfers/{transfer}/cancel', [RootOwnershipController::class, 'cancelFromLink'])->middleware('signed')->name('root-ownership.cancel.store');
});
Route::redirect('/student-parent-login', '/')->name('studentParentLogin');
Route::get('/login', fn () => redirect()->route('landingPage'))->name('login');
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
// FEATURE:authentication - route para sa Role-Based Login and Session Protection.
Route::post($staffLoginPath, [StaffLoginController::class, 'store'])
    ->middleware(array_filter([
        'guest:'.config('fortify.guard'),
        config('fortify.limiters.login') ? 'throttle:'.config('fortify.limiters.login') : null,
    ]))
    ->name('staff.login.store');
Route::middleware('auth')->group(function () {
    // FEATURE:first-login-password - route para sa First-Login Password Setup.
    Route::get('/first-login/password', [FirstLoginPasswordController::class, 'edit'])->name('password.first-login');
    // FEATURE:first-login-password - route para sa First-Login Password Setup.
    Route::put('/first-login/password', [FirstLoginPasswordController::class, 'update'])
        ->middleware('throttle:first-login-password')
        ->name('password.first-login.update');
});
Route::prefix('admin/login-verification')
    ->middleware(['auth', 'role:admin'])
    ->name('admin.login-verification.')
    ->group(function () {
        // FEATURE:admin-login-otp - route para sa Admin Login Email OTP.
        Route::get('/', [AdminLoginVerificationController::class, 'show'])->name('show');
        // FEATURE:admin-login-otp - route para sa Admin Login Email OTP.
        Route::post('/', [AdminLoginVerificationController::class, 'verify'])
            ->middleware('throttle:admin-login-otp-verify')
            ->name('verify');
        // FEATURE:admin-login-otp - route para sa Admin Login Email OTP.
        Route::post('/resend', [AdminLoginVerificationController::class, 'resend'])
            ->middleware('throttle:admin-login-otp-send')
            ->name('resend');
    });
Route::get('/messages/new', [MessageController::class, 'create'])->name('messages.create');
Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
Route::middleware(['auth', 'role:admin,instructor,clinic,registrar,student,parent', EnsureParentPortalEnabled::class])->group(function () {
    // FEATURE:messenger - route para sa Messenger and Attachments.
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    // FEATURE:messenger - route para sa Messenger and Attachments.
    Route::get('/messages/unread-status', [MessageController::class, 'unreadStatus'])->name('messages.unread-status');
    // FEATURE:messenger - route para sa Messenger and Attachments.
    Route::post('/messages/conversation', [MessageController::class, 'sendConversationMessage'])->name('messages.conversation.store');
    Route::post('/messages/{message}/forward-to-parent', [MessageController::class, 'forwardExcuseLetterToParent'])->name('messages.forward-to-parent');
    // FEATURE:excuse-letter-review - route para sa Excuse Letter Review.
    Route::put('/messages/{message}/excuse-letter-review', [MessageController::class, 'reviewExcuseLetter'])->name('messages.excuse-letters.review');
    // FEATURE:messenger - route para sa Messenger and Attachments.
    Route::put('/messages/{message}/read', [MessageController::class, 'markRead'])->name('messages.read');
    // FEATURE:messenger - route para sa Messenger and Attachments.
    Route::get('/messages/{message}/attachment', [MessageController::class, 'downloadAttachment'])->name('messages.attachments.show');
});
Route::middleware(['auth', 'role:admin,instructor,clinic,registrar,student,parent', EnsureParentPortalEnabled::class])->group(function () {
    // FEATURE:reports - route para sa Reports and Exports.
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    // FEATURE:reports - route para sa Reports and Exports.
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
});
Route::get('/attendance-control-panel/login', [AttendanceController::class, 'panelLogin'])->name('attendanceControlPanel.login');
// FEATURE:rfid-attendance - route para sa RFID Attendance Console.
Route::post('/panel-verify', [AttendanceController::class, 'verifyPanelPin'])->name('panelVerify');
// FEATURE:face-recognition - legacy student face check route para sa captured image.
Route::post('/face-recognition/verify-student', [AttendanceController::class, 'verifyStudentFace'])->name('faceRecognition.verifyStudent');
Route::middleware('auth')->group(function () {
    // FEATURE:face-liveness - gumagawa ng single-use AWS session.
    Route::post('/face-liveness/sessions', [FaceLivenessController::class, 'store'])->name('faceLiveness.store');
    // FEATURE:face-liveness - kinukuha ang session result at reference image.
    Route::post('/face-liveness/sessions/{sessionId}/result', [FaceLivenessController::class, 'show'])->name('faceLiveness.show');
});
Route::get('/attendance-evidence/{attendanceLog}/{moment}', [AttendanceController::class, 'evidence'])
    ->middleware(['auth', 'role:admin,instructor,student,parent', EnsureParentPortalEnabled::class])
    ->name('attendance.evidence');

Route::middleware(['auth', 'role:console'])->group(function () {
    Route::get('/attendance-control-panel', [AttendanceController::class, 'controlPanel'])->name('attendanceControlPanel');
    Route::post('/attendance-control-panel/room', [AttendanceController::class, 'selectPanelRoom'])->name('attendanceControlPanel.room');
    Route::post('/attendance-control-panel/logout', [AttendanceController::class, 'panelLogout'])->name('attendanceControlPanel.logout');
    Route::post('/attendance-control-panel/status', [AttendanceController::class, 'panelStatus'])->name('attendanceControlPanel.status');
    // FEATURE:rfid-attendance - route para sa RFID Attendance Console.
    Route::post('/attendance-control-panel/rfid-lookup', [AttendanceController::class, 'lookupRfid'])->name('attendanceControlPanel.lookupRfid');
    // FEATURE:rfid-attendance - route para sa RFID Attendance Console.
    Route::post('/attendance-control-panel/session-state', [AttendanceController::class, 'updatePanelSessionState'])->name('attendanceControlPanel.sessionState');
    // FEATURE:face-recognition - kino-compare ang student capture sa enrolled image.
    // FEATURE:face-liveness - kino-consume ang reference frame kapag enabled.
    Route::post('/attendance-control-panel/student-face-check', [AttendanceController::class, 'studentFaceCheck'])->name('attendanceControlPanel.studentFaceCheck');
    Route::post('/attendance-control-panel/instructor-face-check', [AttendanceController::class, 'instructorFaceCheck'])->name('attendanceControlPanel.instructorFaceCheck');
    // FEATURE:rfid-attendance - route para sa RFID Attendance Console.
    Route::post('/attendance-control-panel/student-tap', [AttendanceController::class, 'recordStudentTap'])->name('attendanceControlPanel.studentTap');
    // FEATURE:rfid-attendance - route para sa RFID Attendance Console.
    Route::post('/attendance-control-panel/attendance-logs', [AttendanceController::class, 'attendanceLogSnapshot'])->name('attendanceControlPanel.attendanceLogs');
    // FEATURE:console-borrowing - route para sa Console Borrowing.
    Route::post('/attendance-control-panel/borrow-items-only', [BorrowController::class, 'borrowItemsOnly'])->name('attendanceControlPanel.borrowItemsOnly');
    Route::post('/attendance-control-panel/verify-face', [AttendanceController::class, 'verifyFace'])->name('attendanceControlPanel.verifyFace');
    // FEATURE:emergency-alerts - route para sa Emergency Alerts and Delivery.
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
    if ($role === 'student' || ($role === 'parent' && SystemSetting::boolean(SystemSetting::PARENT_PORTAL_ENABLED, false))) {
        return redirect()->route('student-parent.dashboard');
    }

    return redirect()->route('landingPage');
})->middleware('auth')->name('dashboard');

Route::prefix('instructor')
    ->middleware(['auth', 'role:instructor'])
    ->name('instructor.')
    ->group(function () {
        // FEATURE:instructor-verification - route para sa Login Verification.
        Route::get('/verify', [InstructorVerificationController::class, 'show'])->name('verify');
        // FEATURE:instructor-verification - route para sa Login Verification.
        Route::post('/verify/face', [InstructorVerificationController::class, 'verifyFace'])->name('verify.face');
        // FEATURE:instructor-verification - route para sa Login Verification.
        Route::post('/verify/otp/send', [InstructorVerificationController::class, 'sendOtp'])->name('verify.otp.send');
        // FEATURE:instructor-verification - route para sa Login Verification.
        Route::post('/verify/otp', [InstructorVerificationController::class, 'verifyOtp'])->name('verify.otp');
        // FEATURE:instructor-verification - route para sa Login Verification.
        Route::post('/verify/security/setup', [InstructorVerificationController::class, 'setupSecurity'])->name('verify.security.setup');
        // FEATURE:instructor-verification - route para sa Login Verification.
        Route::post('/verify/security', [InstructorVerificationController::class, 'verifySecurity'])->name('verify.security');
    });

Route::prefix('registrar')
    ->middleware(['auth', 'role:registrar'])
    ->name('registrar.')
    ->group(function () {
        Route::get('/dashboard', [RegistrarController::class, 'dashboard'])->name('dashboard');
        Route::get('/biometric-enrollment', [RegistrarController::class, 'biometricEnrollment'])->name('biometric-enrollment');
        Route::get('/instructor-face-enrollment', [RegistrarController::class, 'instructorFaceEnrollment'])->name('instructor-face-enrollment');
        // FEATURE:student-rfid-enrollment - route para sa Student RFID Enrollment.
        Route::put('/students/{student}/rfid', [RegistrarController::class, 'updateStudentRfid'])->name('students.rfid');
        // FEATURE:student-face-enrollment - route para sa Student Face Enrollment.
        Route::post('/students/{student}/face', [RegistrarController::class, 'uploadStudentFace'])->name('students.face');
        // FEATURE:student-face-enrollment - route para sa Student Face Enrollment.
        Route::delete('/students/{student}/face/{index}', [RegistrarController::class, 'deleteStudentFace'])->name('students.face.delete');
        // FEATURE:instructor-biometric-enrollment - route para sa Instructor RFID and Face Enrollment.
        Route::put('/faculty/{user}/rfid', [RegistrarController::class, 'updateFacultyRfid'])->name('faculty.rfid');
        // FEATURE:instructor-biometric-enrollment - route para sa Instructor RFID and Face Enrollment.
        Route::post('/faculty/{user}/face', [RegistrarController::class, 'uploadFacultyFace'])->name('faculty.face');
        // FEATURE:instructor-biometric-enrollment - route para sa Instructor RFID and Face Enrollment.
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
            // FEATURE:attendance-review - route para sa Assigned Attendance and Corrections.
            Route::get('/attendance/logs', [AttendanceManagementController::class, 'index'])->name('attendance.logs');
            // FEATURE:attendance-review - route para sa Assigned Attendance and Corrections.
            Route::get('/attendance/subjects/{subject}', [AttendanceManagementController::class, 'dashboard'])->name('attendance.subject');
            // FEATURE:attendance-review - route para sa Assigned Attendance and Corrections.
            Route::get('/attendance/subjects/{subject}/summary', [AttendanceManagementController::class, 'summary'])->name('attendance.summary');
            // FEATURE:attendance-review - route para sa Assigned Attendance and Corrections.
            Route::get('/attendance/subjects/{subject}/students/{student}', [AttendanceManagementController::class, 'student'])->name('attendance.student');
            // FEATURE:attendance-review - route para sa Assigned Attendance and Corrections.
            Route::get('/attendance/subjects/{subject}/sessions/{session}', [AttendanceManagementController::class, 'session'])->name('attendance.session');
            // FEATURE:attendance-review - route para sa Assigned Attendance and Corrections.
            Route::get('/attendance/subjects/{subject}/summary/export/{format}', [AttendanceManagementController::class, 'exportSummary'])->name('attendance.summary.export');
            // FEATURE:attendance-review - route para sa Assigned Attendance and Corrections.
            Route::get('/attendance/subjects/{subject}/sessions/{session}/export/{format}', [AttendanceManagementController::class, 'exportSession'])->name('attendance.session.export');
            Route::get('/attendance/logs/legacy', [AttendanceController::class, 'logs'])->name('attendance.logs.legacy');
            Route::patch('/attendance/logs/status', [AttendanceController::class, 'updateAttendanceStatus'])->name('attendance.logs.status');
            // FEATURE:attendance-review - route para sa Assigned Attendance and Corrections.
            Route::patch('/attendance/online/status', [AttendanceManagementController::class, 'updateOnlineAttendanceStatus'])->name('attendance.online.status');
            Route::post('/attendance/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');
            // FEATURE:messenger - route para sa Messenger and Attachments.
            Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
            Route::post('/messages/{message}/reply', [MessageController::class, 'reply'])->name('messages.reply');
            // FEATURE:online-class-management - route para sa Online Class Management.
            Route::get('/online-classes', [OnlineClassController::class, 'index'])->name('online-classes.index');
            // FEATURE:online-class-management - route para sa Online Class Management.
            Route::post('/online-classes', [OnlineClassController::class, 'store'])->name('online-classes.store');
            // FEATURE:online-class-management - route para sa Online Class Management.
            Route::put('/online-classes/{onlineClass}', [OnlineClassController::class, 'update'])->name('online-classes.update');
            // FEATURE:online-class-management - route para sa Online Class Management.
            Route::put('/online-classes/{onlineClass}/cancel', [OnlineClassController::class, 'cancel'])->name('online-classes.cancel');
            // FEATURE:online-class-management - route para sa Online Class Management.
            Route::delete('/online-classes/{onlineClass}', [OnlineClassController::class, 'destroy'])->name('online-classes.destroy');
            // FEATURE:student-management - route para sa Student and Parent Management.
            Route::get('/students', [StudentsController::class, 'indexAdmin'], ['title' => 'Instructor Management'])->name('students.index');
            // FEATURE:academic-scheduling - route para sa Academic Structure and Scheduling.
            Route::get('/schedules', [ScheduleController::class, 'indexAdmin'])->name('schedules.index');
        });

        Route::middleware('role:admin')->group(function () {
            // FEATURE:academic-year-rollover - route para sa Academic Year Lifecycle and Rollover.
            Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
            // FEATURE:academic-year-rollover - route para sa Academic Year Lifecycle and Rollover.
            Route::post('/academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
            // FEATURE:academic-year-rollover - route para sa Academic Year Lifecycle and Rollover.
            Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->name('academic-years.update');
            // FEATURE:academic-year-rollover - route para sa Academic Year Lifecycle and Rollover.
            Route::post('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');
            // FEATURE:academic-year-rollover - route para sa Academic Year Lifecycle and Rollover.
            Route::post('/academic-years/{academicYear}/close', [AcademicYearController::class, 'close'])->name('academic-years.close');
            // FEATURE:academic-year-rollover - route para sa Academic Year Lifecycle and Rollover.
            Route::post('/academic-years/{academicYear}/archive', [AcademicYearController::class, 'archive'])->name('academic-years.archive');
            // FEATURE:academic-year-rollover - route para sa Academic Year Lifecycle and Rollover.
            Route::post('/academic-years/{academicYear}/reopen', [AcademicYearController::class, 'reopen'])->name('academic-years.reopen');
            // FEATURE:academic-year-rollover - route para sa Academic Year Lifecycle and Rollover.
            Route::get('/academic-years/{academicYear}/rollover-preview', [AcademicYearController::class, 'rolloverPreview'])->name('academic-years.rollover-preview');
            // FEATURE:academic-year-rollover - route para sa Academic Year Lifecycle and Rollover.
            Route::post('/academic-years/{academicYear}/rollover', [AcademicYearController::class, 'rolloverExecute'])->name('academic-years.rollover');
            Route::get('/laboratories', [LaboratoryController::class, 'indexAdmin'])->name('laboratories');
            Route::post('/laboratories', [LaboratoryController::class, 'store'])->name('laboratories.store');
            Route::put('/laboratories/{id}', [LaboratoryController::class, 'update'])->name('laboratories.update');
            Route::delete('/laboratories/{id}', [LaboratoryController::class, 'destroy'])->name('laboratories.destroy');
            // FEATURE:borrowing-management - route para sa Borrowing Oversight and Returns.
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
            Route::post('/subjects/{subject}/offerings', [SubjectController::class, 'storeOffering'])->name('subjects.offerings.store');
            Route::patch('/subject-offerings/{subjectOffering}/instructor', [SubjectController::class, 'removeOfferingInstructor'])->name('subjects.offerings.instructor.remove');
            Route::delete('/subject-offerings/{subjectOffering}', [SubjectController::class, 'destroyOffering'])->name('subjects.offerings.destroy');
            // FEATURE:academic-scheduling - route para sa Academic Structure and Scheduling.
            Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
            // FEATURE:academic-scheduling - route para sa Academic Structure and Scheduling.
            Route::put('/schedules/{id}', [ScheduleController::class, 'update'])->name('schedules.update');
            // FEATURE:academic-scheduling - route para sa Academic Structure and Scheduling.
            Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
            // FEATURE:inventory-management - route para sa Inventory Management.
            Route::get('/inventory', [InventoryController::class, 'indexAdmin'])->name('inventory.index');
            // FEATURE:inventory-management - route para sa Inventory Management.
            Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
            // FEATURE:inventory-management - route para sa Inventory Management.
            Route::put('/inventory/{id}', [InventoryController::class, 'update'])->name('inventory.update');
            // FEATURE:inventory-management - route para sa Inventory Management.
            Route::delete('/inventory/{id}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
            // FEATURE:admin-logs - route para sa Activity and Online Class Logs.
            Route::get('/activity-logs', [ActivityLogController::class, 'indexAdmin'])->name('activity-logs.index');
            // FEATURE:admin-logs - route para sa Activity and Online Class Logs.
            Route::get('/activity-logs/export', [ActivityLogController::class, 'export'])->name('activity-logs.export');
            // FEATURE:user-management - route para sa User and Role Management.
            Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
            // FEATURE:user-management - route para sa User and Role Management.
            Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
            // FEATURE:user-management - route para sa User and Role Management.
            Route::put('/users/{id}', [AdminUserController::class, 'update'])->name('users.update');
            // FEATURE:user-management - route para sa User and Role Management.
            Route::put('/users/{id}/password/reset-default', [AdminUserController::class, 'resetPassword'])
                ->name('users.password.reset-default');
            // FEATURE:user-management - route para sa User and Role Management.
            Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('users.destroy');
            // FEATURE:root-ownership - route para sa Ownership Transfer and Override.
            Route::post('/root-ownership/transfers', [RootOwnershipController::class, 'store'])->middleware('throttle:root-ownership')->name('root-ownership.transfers.store');
            // FEATURE:root-ownership - route para sa Ownership Transfer and Override.
            Route::delete('/root-ownership/transfers/{transfer}', [RootOwnershipController::class, 'cancel'])->middleware('throttle:root-ownership')->name('root-ownership.transfers.cancel');
            // FEATURE:root-ownership - nagsisimula ng emergency override request.
            Route::post('/root-ownership/overrides', [RootOverrideController::class, 'store'])->middleware('throttle:root-ownership')->name('root-ownership.overrides.store');
            // FEATURE:root-ownership - nagrerecord ng approval decision.
            Route::post('/root-ownership/overrides/{override}/decision', [RootOverrideController::class, 'decide'])->middleware('throttle:root-ownership')->name('root-ownership.overrides.decide');
            // FEATURE:admin-logs - hiwalay na online-class audit list.
            Route::get('/online-class-logs', [OnlineClassController::class, 'logs'])->name('online-class-logs.index');
            // FEATURE:admin-logs - export ng online-class audit list.
            Route::get('/online-class-logs/export', [OnlineClassController::class, 'exportLogs'])->name('online-class-logs.export');
            // FEATURE:device-management - route para sa Laboratories and Devices.
            Route::get('/active-devices', [ActiveDeviceController::class, 'index'])->name('active-devices.index');
            // FEATURE:device-management - route para sa Laboratories and Devices.
            Route::post('/active-devices', [ActiveDeviceController::class, 'store'])->name('active-devices.store');
            // FEATURE:device-management - route para sa Laboratories and Devices.
            Route::put('/active-devices/panel-access', [ActiveDeviceController::class, 'updatePanelAccess'])->name('active-devices.panel-access.update');
            // FEATURE:device-management - route para sa Laboratories and Devices.
            Route::put('/active-devices/{device}', [ActiveDeviceController::class, 'update'])->name('active-devices.update');
            // FEATURE:device-management - route para sa Laboratories and Devices.
            Route::delete('/active-devices/{device}', [ActiveDeviceController::class, 'destroy'])->name('active-devices.destroy');
            // FEATURE:device-management - route para sa Laboratories and Devices.
            Route::put('/active-devices/{device}/pin', [ActiveDeviceController::class, 'updatePanelDevicePin'])->name('active-devices.pin.update');
            // FEATURE:device-management - route para sa Laboratories and Devices.
            Route::post('/active-devices/{panelSessionId}/force-logout', [ActiveDeviceController::class, 'forceLogout'])->name('active-devices.force-logout');
            // FEATURE:system-settings - route para sa System Settings.
            Route::get('/settings', [SystemSettingsController::class, 'edit'])->name('settings.edit');
            // FEATURE:system-settings - route para sa System Settings.
            Route::put('/settings', [SystemSettingsController::class, 'update'])->name('settings.update');
            // FEATURE:system-settings - route para sa System Settings.
            Route::post('/settings/sms/providers/{provider}/check', [SystemSettingsController::class, 'checkSmsProvider'])
                ->whereIn('provider', ['semaphore', 'iprog'])
                ->name('settings.sms.providers.check');
            // FEATURE:system-settings - route para sa System Settings.
            Route::post('/settings/emergency-sounds', [SystemSettingsController::class, 'storeEmergencySound'])->name('settings.emergency-sounds.store');
            // FEATURE:system-settings - route para sa System Settings.
            Route::put('/settings/emergency-sounds/{id}/select', [SystemSettingsController::class, 'selectEmergencySound'])->name('settings.emergency-sounds.select');
            // FEATURE:system-settings - route para sa System Settings.
            Route::delete('/settings/emergency-sounds/{id}', [SystemSettingsController::class, 'destroyEmergencySound'])->name('settings.emergency-sounds.destroy');
            Route::get('/strands', [StrandController::class, 'indexAdmin'])->name('strands.index');
            Route::post('/strands', [StrandController::class, 'store'])->name('strands.store');
            Route::put('/strands/{id}', [StrandController::class, 'update'])->name('strands.update');
            Route::delete('/strands/{id}', [StrandController::class, 'destroy'])->name('strands.destroy');
            // FEATURE:student-management - route para sa Student and Parent Management.
            Route::post('/students', [StudentsController::class, 'store'])->name('students.store');
            // FEATURE:student-management - route para sa Student and Parent Management.
            Route::put('/students/{id}', [StudentsController::class, 'update'])->name('students.update');
            Route::put('/students/{id}/password/reset-default', [StudentsController::class, 'resetStudentAccountPassword'])
                ->name('students.password.reset-default');
            // FEATURE:student-management - route para sa Student and Parent Management.
            Route::delete('/students/{id}', [StudentsController::class, 'destroy'])->name('students.destroy');
            // FEATURE:student-management - route para sa Student and Parent Management.
            Route::post('/students/{id}/parents', [StudentsController::class, 'storeParent'])->name('students.parents.store');
            // FEATURE:student-management - route para sa Student and Parent Management.
            Route::put('/students/{id}/parents/{parent}', [StudentsController::class, 'updateParent'])->name('students.parents.update');
            // FEATURE:student-management - route para sa Student and Parent Management.
            Route::delete('/students/{id}/parents/{parent}', [StudentsController::class, 'destroyParent'])->name('students.parents.destroy');
            // FEATURE:instructor-management - route para sa Instructor Management.
            Route::get('/instructors', [InstructorsController::class, 'indexAdmin'])->name('instructors.index');
            // FEATURE:instructor-management - route para sa Instructor Management.
            Route::post('/instructors', [InstructorsController::class, 'store'])->name('instructors.store');
            // FEATURE:instructor-management - route para sa Instructor Management.
            Route::put('/instructors/{id}', [InstructorsController::class, 'update'])->name('instructors.update');
            // FEATURE:instructor-management - route para sa Instructor Management.
            Route::put('/instructors/{id}/password/reset-default', [InstructorsController::class, 'resetPassword'])
                ->name('instructors.password.reset-default');
            Route::put('/instructors/{id}/security-questions/reset', [InstructorsController::class, 'resetSecurityQuestions'])
                ->name('instructors.security-questions.reset');
            // FEATURE:instructor-management - route para sa Instructor Management.
            Route::delete('/instructors/{id}', [InstructorsController::class, 'destroy'])->name('instructors.destroy');
            Route::inertia('/students-management', 'StudentsManagement', ['title' => 'Students Management'])->name('studentsManagement');
            // Route::inertia('/instructors-management', 'InstructorsManagement', ['title' => 'Instructor Management'])->name('instructorsManagement');
            // FEATURE:borrowing-management - route para sa Borrowing Oversight and Returns.
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
        // FEATURE:clinic-records - route para sa Case Logs and Patient History.
        Route::get('/case-logs', [ClinicController::class, 'caseLogs'])->name('case-logs');
        // FEATURE:clinic-records - route para sa Case Logs and Patient History.
        Route::post('/case-logs', [ClinicController::class, 'storeCase'])->name('case-logs.store');
        // FEATURE:clinic-records - route para sa Case Logs and Patient History.
        Route::put('/case-logs/{id}', [ClinicController::class, 'updateCase'])->name('case-logs.update');
        // FEATURE:clinic-records - route para sa Case Logs and Patient History.
        Route::post('/case-logs/{id}/history', [ClinicController::class, 'createHistoryFromCase'])->name('case-logs.history');
        // FEATURE:clinic-records - route para sa Case Logs and Patient History.
        Route::get('/patient-history', [ClinicController::class, 'patientHistory'])->name('patient-history');
        // FEATURE:clinic-records - route para sa Case Logs and Patient History.
        Route::post('/patient-history', [ClinicController::class, 'storeHistory'])->name('patient-history.store');
        // FEATURE:clinic-records - route para sa Case Logs and Patient History.
        Route::put('/patient-history/{id}', [ClinicController::class, 'updateHistory'])->name('patient-history.update');
        // FEATURE:clinic-records - route para sa Case Logs and Patient History.
        Route::delete('/patient-history/{id}', [ClinicController::class, 'destroyHistory'])->name('patient-history.destroy');
        // FEATURE:clinic-reports - route para sa Clinic Reports.
        Route::get('/reports', [ClinicController::class, 'reports'])->name('reports');
        // FEATURE:clinic-reports - route para sa Clinic Reports.
        Route::get('/reports/export', [ClinicController::class, 'exportReports'])->name('reports.export');
        // FEATURE:emergency-configuration - route para sa Emergency Types and Hotlines.
        Route::get('/emergency-hotlines', [EmergencyController::class, 'hotlines'])->name('emergency-hotlines.index');
        // FEATURE:emergency-configuration - route para sa Emergency Types and Hotlines.
        Route::post('/emergency-hotlines', [EmergencyController::class, 'storeHotline'])->name('emergency-hotlines.store');
        // FEATURE:emergency-configuration - route para sa Emergency Types and Hotlines.
        Route::put('/emergency-hotlines/{id}', [EmergencyController::class, 'updateHotline'])->name('emergency-hotlines.update');
        // FEATURE:emergency-configuration - route para sa Emergency Types and Hotlines.
        Route::delete('/emergency-hotlines/{id}', [EmergencyController::class, 'destroyHotline'])->name('emergency-hotlines.destroy');
        // FEATURE:emergency-configuration - route para sa Emergency Types and Hotlines.
        Route::post('/emergency-types', [EmergencyController::class, 'storeType'])->name('emergency-types.store');
        // FEATURE:emergency-configuration - route para sa Emergency Types and Hotlines.
        Route::put('/emergency-types/{id}', [EmergencyController::class, 'updateType'])->name('emergency-types.update');
        // FEATURE:emergency-configuration - route para sa Emergency Types and Hotlines.
        Route::delete('/emergency-types/{id}', [EmergencyController::class, 'destroyType'])->name('emergency-types.destroy');
        // FEATURE:clinic-dispatch - route para sa Emergency Alert Response and Dispatch.
        Route::put('/emergency-alerts/{id}', [EmergencyController::class, 'updateAlertStatus'])->name('emergency-alerts.update');
        // FEATURE:clinic-dispatch - route para sa Emergency Alert Response and Dispatch.
        Route::post('/emergency-alerts/{id}/dispatch', [EmergencyController::class, 'dispatchAlert'])->name('emergency-alerts.dispatch');
    });

Route::prefix('student-parent')
    ->middleware(['auth', 'role:student,parent', EnsureParentPortalEnabled::class])
    ->name('student-parent.')
    ->group(function () {
        // FEATURE:parent-student-view - route para sa Linked Student Dashboard and Attendance.
        Route::get('/dashboard', [StudentsController::class, 'portalDashboard'])->name('dashboard');
        Route::get('/profile', [StudentsController::class, 'portalProfile'])->name('profile.show');
        Route::put('/profile', [StudentsController::class, 'updatePortalProfile'])->name('profile.update');
        Route::put('/password', [StudentsController::class, 'updatePortalPassword'])->name('password.update');
        // FEATURE:student-attendance-history - route para sa Attendance History.
        // FEATURE:parent-student-view - route para sa Linked Student Dashboard and Attendance.
        // FEATURE:student-attendance-history - route para sa Attendance History.
        // FEATURE:parent-student-view - route para sa Linked Student Dashboard and Attendance.
        // FEATURE:student-attendance-history - route para sa Attendance History.
        // FEATURE:parent-student-view - route para sa Linked Student Dashboard and Attendance.
        // FEATURE:student-attendance-history - route para sa Attendance History.
        // FEATURE:parent-student-view - route para sa Linked Student Dashboard and Attendance.
        Route::get('/attendance', [StudentsController::class, 'portalAttendance'])->name('attendance');
        // FEATURE:excuse-letter-submission - route para sa Excuse Letter Submission.
        Route::get('/excuse-letters', [StudentsController::class, 'portalExcuseLetters'])->name('excuse-letters.index');
        // FEATURE:excuse-letter-submission - route para sa Excuse Letter Submission.
        Route::post('/excuse-letters', [StudentsController::class, 'storePortalExcuseLetter'])->name('excuse-letters.store');
        // FEATURE:excuse-letter-approval - route para sa Excuse Letter Approval.
        Route::put('/excuse-letters/{letter}/approve', [StudentsController::class, 'approvePortalExcuseLetter'])->name('excuse-letters.approve');
        Route::get('/excuse-letters/{letter}/download', [StudentsController::class, 'downloadPortalExcuseLetter'])->name('excuse-letters.download');
        Route::get('/excuse-letters/{letter}/attachment', [StudentsController::class, 'downloadPortalExcuseLetterAttachment'])->name('excuse-letters.attachment');
        // FEATURE:messenger - route para sa Messenger and Attachments.
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        // FEATURE:messenger - route para sa Messenger and Attachments.
        Route::post('/messages', [MessageController::class, 'sendConversationMessage'])->name('messages.store');
        // FEATURE:online-class-notifications - route para sa Online Class Notifications.
        Route::get('/notifications', [StudentsController::class, 'portalNotifications'])->name('notifications.index');
        // FEATURE:online-class-notifications - route para sa Online Class Notifications.
        Route::put('/notifications/{notification}/read', [StudentsController::class, 'markPortalNotificationRead'])->name('notifications.read');
        // FEATURE:online-class-join - route para sa Online Class Viewing and Joining.
        Route::get('/online-classes', [OnlineClassController::class, 'studentIndex'])->name('online-classes.index');
        // FEATURE:online-class-join - route para sa Online Class Viewing and Joining.
        Route::post('/online-classes/{onlineClass}/join', [OnlineClassController::class, 'join'])->name('online-classes.join');
    });

// Admin routes

Route::post('/register', [AuthController::class, 'register'])->name('register.store');
