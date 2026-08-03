<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('online-classes:finalize-attendance', function (\App\Services\OnlineClassAttendanceFinalizer $finalizer) {
    $created = $finalizer->finalizeEnded();
    $this->info("Created {$created} online-class absence record(s).");
})->purpose('Persist Absent attendance for students who did not join ended online classes');

Schedule::command('online-classes:finalize-attendance')->everyMinute()->withoutOverlapping();

Artisan::command('db:schema-notes', function () {
    $descriptions = [
        'emergency_types' => 'Configurable emergency buttons/messages shown on the attendance panel.',
        'emergency_alerts' => 'Emergency notifications triggered from the attendance panel for clinic/admin response.',
        'clinic_cases' => 'Clinic case log entries, optionally linked to emergency alerts.',
        'patient_histories' => 'Long-term clinic history records for students, instructors, or visitors.',
        'users' => 'Application accounts and roles such as admin, instructor, clinic, console.',
        'students' => 'Student master records, RFID tags, sections, and school year.',
        'schedules' => 'Class schedules by section, instructor, room/laboratory, day, and time.',
        'attendance_logs' => 'Per-student attendance tap log records.',
        'attendances' => 'Attendance records tied to students and schedules.',
        'attendance_sessions' => 'Room-level live attendance sessions.',
        'rfid_panel_sessions' => 'Attendance panel room state and metadata.',
    ];

    foreach (\Illuminate\Support\Facades\DB::select('SHOW TABLES') as $row) {
        $table = array_values((array) $row)[0];
        $this->newLine();
        $this->line("<info>{$table}</info> - ".($descriptions[$table] ?? 'Application data table.'));

        foreach (\Illuminate\Support\Facades\Schema::getColumns($table) as $column) {
            $nullable = ($column['nullable'] ?? false) ? 'nullable' : 'required';
            $this->line("  - {$column['name']} ({$column['type_name']}, {$nullable})");
        }
    }
})->purpose('List all database tables and columns with short table descriptions');

Artisan::command('system:features', function () {
    $this->info('RFID Attendance Monitoring, Borrowing, Inventory, and Clinic Emergency System');

    $this->newLine();
    $this->line('<comment>Roles</comment>');
    $this->line('  - admin: full management access');
    $this->line('  - instructor: dashboard, own schedules, handled students, own attendance records');
    $this->line('  - clinic: clinic dashboard, emergency notifications, case logs, patient history, reports');
    $this->line('  - console: attendance control panel access');

    $this->newLine();
    $this->line('<comment>Main Modules</comment>');
    $this->line('  - Dashboard');
    $this->line('  - Schedule management with room/laboratory assignment');
    $this->line('  - Student management by strand, section, grade, and school year');
    $this->line('  - RFID attendance scanner and attendance logs');
    $this->line('  - Attendance control panel with instructor/student RFID flow');
    $this->line('  - Borrowing and inventory management');
    $this->line('  - RFID assignment for students and instructors');
    $this->line('  - Activity logs');

    $this->newLine();
    $this->line('<comment>Emergency and Clinic</comment>');
    $this->line('  - Database-backed emergency types and messages');
    $this->line('  - Attendance panel emergency table');
    $this->line('  - Clinic emergencies: Seizure, Fainting, Severe Bleeding, Asthma/Allergy, General Distress');
    $this->line('  - Disaster/general emergencies: Fire Emergency, Earthquake, Other Emergency');
    $this->line('  - Emergency alerts saved to the database');
    $this->line('  - Clinic dashboard counts and emergency notifications');
    $this->line('  - Clinic case logs, patient history, and reports');

    $this->newLine();
    $this->line('<comment>Useful Commands</comment>');
    $this->line('  - php artisan db:schema-notes');
    $this->line('  - php artisan migrate');
    $this->line('  - php artisan db:seed --class=EmergencySeeder');
    $this->line('  - php artisan db:seed --class=SeniorHighAcademicSeeder');
    $this->line('  - php artisan route:list');
})->purpose('Show the current system roles, modules, and feature summary');
