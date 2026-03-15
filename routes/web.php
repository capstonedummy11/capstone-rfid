<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::inertia('/', 'LandingPage')->name('landingPage');
Route::inertia('/about', 'About')->name('about');
Route::inertia('/attendance-scanner', 'AttendanceScanner')->name('attendanceScanner');
Route::inertia('/rfid-registration', 'RFIDRegistration')->name('rfidRegistration');
Route::inertia('/classes', 'Classes')->name('classes');
Route::inertia('/attendance-logs', 'AttendanceLogs')->name('attendanceLogs');
Route::inertia('/register', 'Auth/Register')->name('register_page');

Route::prefix('admin')
    //->middleware(['auth', 'role:admin'])
    ->middleware(['auth'])
    ->name('admin.')
    ->group(function () {
        Route::inertia('/dashboard', 'Auth/Admin/Dashboard', ['title' => 'Dashboard'])->name('dashboard');
        //Route::inertia('/instructors-management', 'InstructorsManagement', ['title' => 'Instructor Management'])->name('instructorsManagement');
        Route::inertia('/students-management', 'StudentsManagement')->name('studentsManagement');
        Route::inertia('/laboratories', 'Auth/Admin/Laboratories', ['title' => 'Laboratories'])->name('laboratories');
    });


//Admin routes

Route::post('/register', [AuthController::class, 'register'])->name('register');
