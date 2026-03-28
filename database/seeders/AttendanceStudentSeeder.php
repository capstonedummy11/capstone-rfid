<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Section;
use App\Models\Students;
use Illuminate\Database\Seeder;

class AttendanceStudentSeeder extends Seeder
{
  public function run(): void
  {
    $bsit = Course::query()->firstOrCreate(
      ['course_code' => 'BSIT'],
      [
        'course_name' => 'Bachelor of Science in Information Technology',
        'department' => 'College of Computing',
        'status' => 'active',
      ]
    );

    $section3A = Section::query()->firstOrCreate(
      ['course_id' => $bsit->course_id, 'section_name' => '3A'],
      [
        'year_level' => 3,
        'semester' => '2nd',
        'school_year' => '2025-2026',
        'status' => 'active',
      ]
    );

    $students = [
      [
        'student_number' => '2023-0001',
        'rfid_tag' => 'STU-2001',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => null,
        'gender' => 'female',
        'email' => 'maria.santos.attendance@school.edu',
        'year_level' => 2,
        'course_id' => $bsit->course_id,
        'section_id' => $section3A->section_id,
      ],
      [
        'student_number' => '2023-0002',
        'rfid_tag' => 'STU-2002',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'middle_name' => null,
        'gender' => 'male',
        'email' => 'juan.delacruz.attendance@school.edu',
        'year_level' => 2,
        'course_id' => $bsit->course_id,
        'section_id' => $section3A->section_id,
      ],
      [
        'student_number' => '2023-0003',
        'rfid_tag' => 'STU-2003',
        'first_name' => 'Elena',
        'last_name' => 'Garcia',
        'middle_name' => null,
        'gender' => 'female',
        'email' => 'elena.garcia.attendance@school.edu',
        'year_level' => 3,
        'course_id' => $bsit->course_id,
        'section_id' => $section3A->section_id,
      ],
    ];

    Students::query()
      ->where('student_number', '2024-0112')
      ->update([
        'rfid_tag' => 'ARCHIVED-STU-2004',
        'email' => 'archived.stu2004@school.edu',
        'status' => 'inactive',
      ]);

    foreach ($students as $student) {
      Students::query()->updateOrCreate(
        ['rfid_tag' => $student['rfid_tag']],
        [
          'student_number' => $student['student_number'],
          'section_id' => $student['section_id'],
          'course_id' => $student['course_id'],
          'first_name' => $student['first_name'],
          'last_name' => $student['last_name'],
          'middle_name' => $student['middle_name'],
          'gender' => $student['gender'],
          'email' => $student['email'],
          'year_level' => $student['year_level'],
          'semester' => '2nd',
          'school_year' => '2025-2026',
          'status' => 'active',
        ]
      );
    }
  }
}