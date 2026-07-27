<?php

namespace Database\Seeders;

use App\Models\Section;
use App\Models\Strand;
use App\Models\Students;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentParentAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $student = $this->resolveStudent();
        $studentEmail = $student->email ?: 'andrea.santos@student.sample.com';

        if ($student->email !== $studentEmail) {
            $student->update(['email' => $studentEmail]);
        }

        $studentUser = User::updateOrCreate(
            ['email' => $studentEmail],
            [
                'name' => trim($student->first_name.' '.$student->last_name),
                'email' => $studentEmail,
                'password' => Hash::make('sample'),
                'role' => 'student',
                'phone' => $student->phone,
                'gender' => $student->gender,
                'rfid_tag' => null,
            ],
        );

        $parentUser = User::updateOrCreate(
            ['email' => 'parent.andrea.santos@sample.com'],
            [
                'name' => 'Maria Santos',
                'email' => 'parent.andrea.santos@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'parent',
                'phone' => '09170001101',
                'gender' => 'female',
                'rfid_tag' => null,
            ],
        );

        $parentUser->linkedStudents()->syncWithoutDetaching([
            $student->student_id => ['relationship' => 'mother'],
        ]);

        $secondStudent = $this->resolveSecondStudent($student);
        $secondStudentEmail = $secondStudent->email ?: 'miguel.reyes@student.sample.com';

        if ($secondStudent->email !== $secondStudentEmail) {
            $secondStudent->update(['email' => $secondStudentEmail]);
        }

        $secondStudentUser = User::updateOrCreate(
            ['email' => $secondStudentEmail],
            [
                'name' => trim($secondStudent->first_name.' '.$secondStudent->last_name),
                'email' => $secondStudentEmail,
                'password' => Hash::make('sample'),
                'role' => 'student',
                'phone' => $secondStudent->phone,
                'gender' => $secondStudent->gender,
                'rfid_tag' => null,
            ],
        );

        $this->command?->info('Seeded student account: '.$studentUser->email.' / sample');
        $this->command?->info('Seeded parent account: '.$parentUser->email.' / sample');
        $this->command?->info('Seeded student account: '.$secondStudentUser->email.' / sample');
        $this->command?->info('Linked parent account to student: '.$student->student_number);
    }

    private function resolveStudent(): Students
    {
        $student = Students::query()
            ->where('student_number', 'SHS-ICT-1101')
            ->first();

        if ($student) {
            return $student;
        }

        $student = Students::query()->orderBy('student_id')->first();

        if ($student) {
            return $student;
        }

        $strand = Strand::updateOrCreate(
            ['strand_code' => 'ICT'],
            [
                'strand_name' => 'Information and Communications Technology',
                'department' => 'Senior High School',
                'status' => 'active',
            ],
        );

        $section = Section::updateOrCreate(
            ['section_name' => 'ICT 11-A', 'school_year' => '2026-2027'],
            [
                'strand_id' => $strand->strand_id,
                'year_level' => 11,
                'semester' => '1st Semester',
                'status' => 'active',
            ],
        );

        return Students::updateOrCreate(
            ['student_number' => 'SHS-ICT-1101'],
            [
                'section_id' => $section->section_id,
                'strand_id' => $strand->strand_id,
                'first_name' => 'Andrea',
                'middle_name' => null,
                'last_name' => 'Santos',
                'gender' => 'female',
                'email' => 'andrea.santos@student.sample.com',
                'phone' => '09170001101',
                'year_level' => 11,
                'semester' => $section->semester,
                'school_year' => $section->school_year,
                'rfid_tag' => 'RFID-STUDENT-1101',
                'face_images' => [],
                'status' => 'active',
            ],
        );
    }

    private function resolveSecondStudent(Students $fallbackStudent): Students
    {
        $student = Students::query()
            ->where('student_number', 'SHS-ICT-1102')
            ->first();

        if ($student) {
            return $student;
        }

        return Students::updateOrCreate(
            ['student_number' => 'SHS-ICT-1102'],
            [
                'section_id' => $fallbackStudent->section_id,
                'strand_id' => $fallbackStudent->strand_id,
                'first_name' => 'Miguel',
                'middle_name' => null,
                'last_name' => 'Reyes',
                'gender' => 'male',
                'email' => 'miguel.reyes@student.sample.com',
                'phone' => '09170001102',
                'year_level' => $fallbackStudent->year_level,
                'semester' => $fallbackStudent->semester,
                'school_year' => $fallbackStudent->school_year,
                'rfid_tag' => 'RFID-STUDENT-1102',
                'face_images' => [],
                'status' => 'active',
            ],
        );
    }
}
