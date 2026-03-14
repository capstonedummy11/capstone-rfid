<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::inertia('/', 'LandingPage')->name('landingPage');
Route::inertia('/about', 'About')->name('about');
Route::inertia('/attendance-scanner', 'AttendanceScanner')->name('attendanceScanner');
Route::inertia('/rfid-registration', 'RFIDRegistration')->name('rfidRegistration');
Route::inertia('/students-management', 'StudentsManagement')->name('studentsManagement');
Route::inertia('/instructors-management', 'InstructorsManagement')->name('instructorsManagement');
Route::inertia('/classes', 'Classes')->name('classes');
Route::inertia('/attendance-logs', 'AttendanceLogs')->name('attendanceLogs');
Route::inertia('/register', 'Auth/Register')->name('register_page');

Route::post('/register', [AuthController::class, 'register'])->name('register');
