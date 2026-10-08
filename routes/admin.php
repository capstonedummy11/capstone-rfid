<?php

// Admin-only routes. All declarations retain an explicit admin role middleware.

use App\Http\Controllers\Admin\AcademicYears\AcademicYearController;
use App\Http\Controllers\Admin\ActiveDevices\ActiveDeviceController;
use App\Http\Controllers\Admin\ActivityLogs\ActivityLogController;
use App\Http\Controllers\Admin\LoginVerification\AdminLoginVerificationController;
use App\Http\Controllers\Admin\UserManagement\AdminUserController;
use App\Http\Controllers\Shared\Borrowing\BorrowController;
use App\Http\Controllers\Admin\Instructors\InstructorsController;
use App\Http\Controllers\Admin\Inventory\InventoryController;
use App\Http\Controllers\Admin\Inventory\ItemController;
use App\Http\Controllers\Admin\Laboratories\LaboratoryController;
use App\Http\Controllers\Shared\OnlineClasses\OnlineClassController;
use App\Http\Controllers\Admin\Rfid\RfidController;
use App\Http\Controllers\Shared\RootOwnership\RootOverrideController;
use App\Http\Controllers\Shared\RootOwnership\RootOwnershipController;
use App\Http\Controllers\Shared\Schedules\ScheduleController;
use App\Http\Controllers\Admin\Sections\SectionController;
use App\Http\Controllers\Admin\Strands\StrandController;
use App\Http\Controllers\Shared\Students\StudentsController;
use App\Http\Controllers\Admin\Subjects\SubjectController;
use App\Http\Controllers\Shared\SystemSettings\SystemSettingsController;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::prefix('admin/login-verification')
    ->middleware(['auth', 'role:admin'])
    ->name('admin.login-verification.')
    ->group(function () {
        // Admin Login Verification
        Route::get('/', [AdminLoginVerificationController::class, 'show'])->name('show');
        Route::post('/', [AdminLoginVerificationController::class, 'verify'])
            ->middleware('throttle:admin-login-otp-verify')
            ->name('verify');
        Route::post('/resend', [AdminLoginVerificationController::class, 'resend'])
            ->middleware('throttle:admin-login-otp-send')
            ->name('resend');
    });

Route::prefix('admin')
    ->middleware(['auth', 'role:admin'])
    ->name('admin.')
    ->group(function () {
        // Academic Year Management
        Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
        Route::post('/academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
        Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->name('academic-years.update');
        Route::post('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');
        Route::post('/academic-years/{academicYear}/close', [AcademicYearController::class, 'close'])->name('academic-years.close');
        Route::post('/academic-years/{academicYear}/archive', [AcademicYearController::class, 'archive'])->name('academic-years.archive');
        Route::post('/academic-years/{academicYear}/reopen', [AcademicYearController::class, 'reopen'])->name('academic-years.reopen');
        Route::get('/academic-years/{academicYear}/rollover-preview', [AcademicYearController::class, 'rolloverPreview'])->name('academic-years.rollover-preview');
        Route::post('/academic-years/{academicYear}/rollover', [AcademicYearController::class, 'rolloverExecute'])->name('academic-years.rollover');

        // Laboratory Management
        Route::get('/laboratories', [LaboratoryController::class, 'indexAdmin'])->name('laboratories');
        Route::post('/laboratories', [LaboratoryController::class, 'store'])->name('laboratories.store');
        Route::put('/laboratories/{id}', [LaboratoryController::class, 'update'])->name('laboratories.update');
        Route::delete('/laboratories/{id}', [LaboratoryController::class, 'destroy'])->name('laboratories.destroy');

        // Borrowing Management
        Route::get('/borrow', [BorrowController::class, 'index'])->name('borrow');

        // RFID Management
        Route::get('/rfid', [RfidController::class, 'index'])->name('rfid');
        Route::put('/rfid/{type}/{id}', [RfidController::class, 'update'])->name('rfid.update');
        Route::delete('/rfid/{type}/{id}', [RfidController::class, 'destroy'])->name('rfid.destroy');

        // Section Management
        Route::get('/sections', [SectionController::class, 'indexAdmin'])->name('sections.index');
        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::put('/sections/{id}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('/sections/{id}', [SectionController::class, 'destroy'])->name('sections.destroy');

        // Subject Management
        Route::get('/subjects', [SubjectController::class, 'indexAdmin'])->name('subjects.index');
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::put('/subjects/{id}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::delete('/subjects/{id}', [SubjectController::class, 'destroy'])->name('subjects.destroy');
        Route::post('/subjects/{subject}/offerings', [SubjectController::class, 'storeOffering'])->name('subjects.offerings.store');
        Route::patch('/subject-offerings/{subjectOffering}/instructor/assign', [SubjectController::class, 'assignOfferingInstructor'])->name('subjects.offerings.instructor.assign');
        Route::patch('/subject-offerings/{subjectOffering}/instructor', [SubjectController::class, 'removeOfferingInstructor'])->name('subjects.offerings.instructor.remove');
        Route::delete('/subject-offerings/{subjectOffering}', [SubjectController::class, 'destroyOffering'])->name('subjects.offerings.destroy');

        // Schedule Management
        Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::put('/schedules/{id}', [ScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');

        // Inventory Management
        Route::get('/inventory', [InventoryController::class, 'indexAdmin'])->name('inventory.index');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::put('/inventory/{id}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::delete('/inventory/{id}', [InventoryController::class, 'destroy'])->name('inventory.destroy');

        // Activity Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'indexAdmin'])->name('activity-logs.index');
        Route::get('/activity-logs/export', [ActivityLogController::class, 'export'])->name('activity-logs.export');

        // User Management
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::put('/users/{id}', [AdminUserController::class, 'update'])->name('users.update');
        Route::put('/users/{id}/password/reset-default', [AdminUserController::class, 'resetPassword'])
            ->name('users.password.reset-default');
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        // Root Ownership
        Route::post('/root-ownership/transfers', [RootOwnershipController::class, 'store'])->middleware('throttle:root-ownership')->name('root-ownership.transfers.store');
        Route::delete('/root-ownership/transfers/{transfer}', [RootOwnershipController::class, 'cancel'])->middleware('throttle:root-ownership')->name('root-ownership.transfers.cancel');
        Route::post('/root-ownership/overrides', [RootOverrideController::class, 'store'])->middleware('throttle:root-ownership')->name('root-ownership.overrides.store');
        Route::post('/root-ownership/overrides/{override}/decision', [RootOverrideController::class, 'decide'])->middleware('throttle:root-ownership')->name('root-ownership.overrides.decide');

        // Online Class Logs
        Route::get('/online-class-logs', [OnlineClassController::class, 'logs'])->name('online-class-logs.index');
        Route::get('/online-class-logs/export', [OnlineClassController::class, 'exportLogs'])->name('online-class-logs.export');

        // Device Management
        Route::get('/active-devices', [ActiveDeviceController::class, 'index'])->name('active-devices.index');
        Route::post('/active-devices', [ActiveDeviceController::class, 'store'])->name('active-devices.store');
        Route::put('/active-devices/panel-access', [ActiveDeviceController::class, 'updatePanelAccess'])->name('active-devices.panel-access.update');
        Route::put('/active-devices/{device}', [ActiveDeviceController::class, 'update'])->name('active-devices.update');
        Route::delete('/active-devices/{device}', [ActiveDeviceController::class, 'destroy'])->name('active-devices.destroy');
        Route::put('/active-devices/{device}/pin', [ActiveDeviceController::class, 'updatePanelDevicePin'])->name('active-devices.pin.update');
        Route::post('/active-devices/{panelSessionId}/force-logout', [ActiveDeviceController::class, 'forceLogout'])->name('active-devices.force-logout');

        // System Settings
        Route::get('/settings', [SystemSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SystemSettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/sms/providers/{provider}/check', [SystemSettingsController::class, 'checkSmsProvider'])
            ->whereIn('provider', ['semaphore', 'iprog'])
            ->name('settings.sms.providers.check');
        Route::post('/settings/emergency-sounds', [SystemSettingsController::class, 'storeEmergencySound'])->name('settings.emergency-sounds.store');
        Route::put('/settings/emergency-sounds/{id}/select', [SystemSettingsController::class, 'selectEmergencySound'])->name('settings.emergency-sounds.select');
        Route::delete('/settings/emergency-sounds/{id}', [SystemSettingsController::class, 'destroyEmergencySound'])->name('settings.emergency-sounds.destroy');

        // Strand Management
        Route::get('/strands', [StrandController::class, 'indexAdmin'])->name('strands.index');
        Route::post('/strands', [StrandController::class, 'store'])->name('strands.store');
        Route::put('/strands/{id}', [StrandController::class, 'update'])->name('strands.update');
        Route::delete('/strands/{id}', [StrandController::class, 'destroy'])->name('strands.destroy');

        // Student Management
        Route::post('/students', [StudentsController::class, 'store'])->name('students.store');
        Route::put('/students/{id}', [StudentsController::class, 'update'])->name('students.update');
        Route::put('/students/{id}/password/reset-default', [StudentsController::class, 'resetStudentAccountPassword'])
            ->name('students.password.reset-default');
        Route::delete('/students/{id}', [StudentsController::class, 'destroy'])->name('students.destroy');
        Route::post('/students/{id}/parents', [StudentsController::class, 'storeParent'])->name('students.parents.store');
        Route::put('/students/{id}/parents/{parent}', [StudentsController::class, 'updateParent'])->name('students.parents.update');
        Route::delete('/students/{id}/parents/{parent}', [StudentsController::class, 'destroyParent'])->name('students.parents.destroy');

        // Instructor Management
        Route::get('/instructors', [InstructorsController::class, 'indexAdmin'])->name('instructors.index');
        Route::post('/instructors', [InstructorsController::class, 'store'])->name('instructors.store');
        Route::put('/instructors/{id}', [InstructorsController::class, 'update'])->name('instructors.update');
        Route::put('/instructors/{id}/password/reset-default', [InstructorsController::class, 'resetPassword'])
            ->name('instructors.password.reset-default');
        Route::put('/instructors/{id}/security-questions/reset', [InstructorsController::class, 'resetSecurityQuestions'])
            ->name('instructors.security-questions.reset');
        Route::delete('/instructors/{id}', [InstructorsController::class, 'destroy'])->name('instructors.destroy');
        // Route::inertia('/instructors-management', 'InstructorsManagement', ['title' => 'Instructor Management'])->name('instructorsManagement');

        // Student Management
        Route::inertia('/students-management', 'Admin/StudentsManagement/StudentsManagementPage', ['title' => 'Students Management'])->name('studentsManagement');

        // Borrowing Management
        Route::post('/borrow/return-items', [BorrowController::class, 'returnItems'])->name('borrow.returnItems');

        // Inventory Management
        // Route::inertia('/inventory', 'Admin/Inventory/InventoryPage', ['title' => 'Inventory', 'items' => fn() => \App\Models\Item::all(),])->name('inventory');
        Route::get('/inventory', function () {
            if (! SystemSetting::boolean(SystemSetting::INVENTORY_ENABLED, false)) {
                return redirect()->route('admin.settings.edit')->with('success', 'Inventory is currently disabled.');
            }

            return Inertia::render('Admin/Inventory/InventoryPage', [
                'title' => 'Inventory',
                'items' => \App\Models\Item::all(),
            ]);
        })->name('inventory');

        // Inventory Items
        Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
        Route::post('/items', [ItemController::class, 'store'])->name('items.store');
    });
