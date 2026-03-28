<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InstructorSeeder extends Seeder
{
  public function run(): void
  {
    // ── Courses ──────────────────────────────────────────────────────────
    $bsit = Course::updateOrCreate(
      ['course_code' => 'BSIT'],
      [
        'course_name' => 'Bachelor of Science in Information Technology',
        'department' => 'College of Computing',
        'status' => 'active',
      ]
    );

    $bscs = Course::updateOrCreate(
      ['course_code' => 'BSCS'],
      [
        'course_name' => 'Bachelor of Science in Computer Science',
        'department' => 'College of Computing',
        'status' => 'active',
      ]
    );

    // ── Sections ─────────────────────────────────────────────────────────
    // BSIT 3A  (Prof. Andrea Cruz)
    $bsit3A = Section::updateOrCreate(
      ['course_id' => $bsit->course_id, 'section_name' => '3A'],
      [
        'year_level' => 3,
        'semester' => '2nd',
        'school_year' => '2025-2026',
        'status' => 'active',
      ]
    );

    // BSCS 2B  (Prof. Miguel Santos)
    $bscs2B = Section::updateOrCreate(
      ['course_id' => $bscs->course_id, 'section_name' => '2B'],
      [
        'year_level' => 2,
        'semester' => '2nd',
        'school_year' => '2025-2026',
        'status' => 'active',
      ]
    );

    // ── Instructors (users) ───────────────────────────────────────────────
    $andrea = User::updateOrCreate(
      ['email' => 'andrea.cruz@school.edu'],
      [
        'name' => 'Prof. Andrea Cruz',
        'password' => Hash::make('password'),
        'role' => 'instructor',
        'rfid_tag' => 'INS-1001',
      ]
    );

    $miguel = User::updateOrCreate(
      ['email' => 'miguel.santos@school.edu'],
      [
        'name' => 'Prof. Miguel Santos',
        'password' => Hash::make('password'),
        'role' => 'instructor',
        'rfid_tag' => 'INS-1002',
      ]
    );

    // ── Schedules ─────────────────────────────────────────────────────────
    // Prof. Andrea Cruz — Systems Analysis and Design, Mon 8:00 AM–10:00 AM, RFID Laboratory
    Schedule::updateOrCreate(
      ['section_id' => $bsit3A->section_id, 'subject_code' => 'SAD'],
      [
        'weekdays' => 'Mon',
        'time_start' => '08:00:00',
        'time_end' => '10:00:00',
        'room' => 'RFID Laboratory',
      ]
    );

    // Prof. Miguel Santos — Database Management Systems, Tue 1:00 PM–3:00 PM, Computer Lab 2
    Schedule::updateOrCreate(
      ['section_id' => $bscs2B->section_id, 'subject_code' => 'DBMS'],
      [
        'weekdays' => 'Tue',
        'time_start' => '13:00:00',
        'time_end' => '15:00:00',
        'room' => 'Computer Lab 2',
      ]
    );

    // ── Additional Friday 20:30-23:00 Instructors ──────────────────────────
    // Prof. Robert Johnson — Web Development, Fri 20:30–23:00, RFID Laboratory
    $robert = User::updateOrCreate(
      ['email' => 'robert.johnson@school.edu'],
      [
        'name' => 'Prof. Robert Johnson',
        'password' => Hash::make('password'),
        'role' => 'instructor',
        'rfid_tag' => 'INS-1003',
      ]
    );

    Schedule::updateOrCreate(
      ['section_id' => $bsit3A->section_id, 'subject_code' => 'WEB-DEV'],
      [
        'weekdays' => 'Fri',
        'time_start' => '20:30:00',
        'time_end' => '23:00:00',
        'room' => 'RFID Laboratory',
      ]
    );

    // Prof. Sarah Williams — Data Structures, Fri 20:30–23:00, ComLab 1
    $sarah = User::updateOrCreate(
      ['email' => 'sarah.williams@school.edu'],
      [
        'name' => 'Prof. Sarah Williams',
        'password' => Hash::make('password'),
        'role' => 'instructor',
        'rfid_tag' => 'INS-1004',
      ]
    );

    Schedule::updateOrCreate(
      ['section_id' => $bscs2B->section_id, 'subject_code' => 'DATA-STRUCT'],
      [
        'weekdays' => 'Sat',
        'time_start' => '00:00:00',
        'time_end' => '23:00:00',
        'room' => 'ComLab 1',
      ]
    );

    // ── Subjects (used to verify schedule ownership per instructor) ────────
    Subject::updateOrCreate(
      ['subject_code' => 'SAD', 'section_id' => $bsit3A->section_id],
      [
        'user_id' => $andrea->user_id,
        'subject_name' => 'Systems Analysis and Design',
        'year_level' => $bsit3A->year_level,
        'department' => 'College of Computing',
        'unit' => 3,
        'semester' => '2nd',
      ]
    );

    Subject::updateOrCreate(
      ['subject_code' => 'DBMS', 'section_id' => $bscs2B->section_id],
      [
        'user_id' => $miguel->user_id,
        'subject_name' => 'Database Management Systems',
        'year_level' => $bscs2B->year_level,
        'department' => 'College of Computing',
        'unit' => 3,
        'semester' => '2nd',
      ]
    );

    Subject::updateOrCreate(
      ['subject_code' => 'WEB-DEV', 'section_id' => $bsit3A->section_id],
      [
        'user_id' => $robert->user_id,
        'subject_name' => 'Web Development',
        'year_level' => $bsit3A->year_level,
        'department' => 'College of Computing',
        'unit' => 3,
        'semester' => '2nd',
      ]
    );

    Subject::updateOrCreate(
      ['subject_code' => 'DATA-STRUCT', 'section_id' => $bscs2B->section_id],
      [
        'user_id' => $sarah->user_id,
        'subject_name' => 'Data Structures',
        'year_level' => $bscs2B->year_level,
        'department' => 'College of Computing',
        'unit' => 3,
        'semester' => '2nd',
      ]
    );
  }
}
