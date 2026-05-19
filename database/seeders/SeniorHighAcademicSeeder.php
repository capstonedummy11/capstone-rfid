<?php

namespace Database\Seeders;

use App\Models\Instructor;
use App\Models\Laboratory;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Strand;
use App\Models\Students;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SeniorHighAcademicSeeder extends Seeder
{
    /**
     * Seed sample senior high sections, laboratories, subjects, and instructor schedules.
     */
    public function run(): void
    {
        $schoolYear = '2026-2027';

        $user = User::updateOrCreate(
            ['email' => 'instructor@sample.com'],
            [
                'name' => 'Sample Instructor',
                'email' => 'instructor@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'instructor',
                'rfid_tag' => 'RFID-INSTRUCTOR-SAMPLE',
            ],
        );

        $strand = Strand::updateOrCreate(
            ['strand_code' => 'TVL-ICT'],
            [
                'strand_name' => 'Technical-Vocational-Livelihood - ICT',
                'department' => 'Senior High School',
                'status' => 'active',
            ],
        );

        $instructor = Instructor::updateOrCreate(
            ['user_id' => $user->user_id],
            [
                'strand_id' => $strand->strand_id,
                'instructor_number' => 'INS-SAMPLE-001',
                'status' => 'active',
            ],
        );

        $laboratories = collect(range(1, 5))->mapWithKeys(function (int $number) {
            $laboratory = Laboratory::updateOrCreate(
                ['name' => "Laboratory {$number}"],
                [
                    'description' => "Senior High ICT Laboratory {$number}",
                    'location' => "Building A - Room {$number}",
                    'status' => 'active',
                ],
            );

            return [$number => $laboratory];
        });

        $sections = collect(range(1, 4))->mapWithKeys(function (int $number) use ($strand, $schoolYear) {
            $section = Section::updateOrCreate(
                [
                    'section_name' => "Section {$number}",
                    'school_year' => $schoolYear,
                ],
                [
                    'strand_id' => $strand->strand_id,
                    'year_level' => $number <= 2 ? 11 : 12,
                    'semester' => '1st Semester',
                    'status' => 'active',
                ],
            );

            return [$number => $section];
        });

        $subjects = [
            ['code' => 'ORAL-COM', 'name' => 'Oral Communication in Context', 'unit' => 3],
            ['code' => 'GEN-MATH', 'name' => 'General Mathematics', 'unit' => 3],
            ['code' => 'EARTH-LIFE', 'name' => 'Earth and Life Science', 'unit' => 3],
            ['code' => 'PER-DEV', 'name' => 'Personal Development', 'unit' => 3],
            ['code' => 'PEH-1', 'name' => 'Physical Education and Health 1', 'unit' => 2],
            ['code' => 'EMPOWER-TECH', 'name' => 'Empowerment Technologies', 'unit' => 3],
            ['code' => 'PRAC-RES-1', 'name' => 'Practical Research 1', 'unit' => 3],
            ['code' => 'ENTREP', 'name' => 'Entrepreneurship', 'unit' => 3],
        ];

        foreach ($sections as $sectionNumber => $section) {
            for ($studentNumber = 1; $studentNumber <= 2; $studentNumber++) {
                Students::updateOrCreate(
                    ['student_number' => sprintf('SHS-%d%02d', $sectionNumber, $studentNumber)],
                    [
                        'section_id' => $section->section_id,
                        'strand_id' => $strand->strand_id,
                        'first_name' => "Student{$sectionNumber}{$studentNumber}",
                        'middle_name' => null,
                        'last_name' => "Section{$sectionNumber}",
                        'gender' => $studentNumber % 2 === 0 ? 'female' : 'male',
                        'email' => sprintf('student%d%d@sample.com', $sectionNumber, $studentNumber),
                        'phone' => null,
                        'year_level' => $section->year_level,
                        'semester' => $section->semester,
                        'school_year' => $section->school_year,
                        'rfid_tag' => sprintf('RFID-STUDENT-S%d%d', $sectionNumber, $studentNumber),
                        'status' => 'active',
                    ],
                );
            }

            foreach ($subjects as $subjectIndex => $subjectData) {
                $subject = Subject::updateOrCreate(
                    [
                        'subject_code' => "{$subjectData['code']}-S{$sectionNumber}",
                        'section_id' => $section->section_id,
                    ],
                    [
                        'user_id' => $user->user_id,
                        'subject_name' => $subjectData['name'],
                        'subject_description' => 'Philippines Senior High School subject.',
                        'department' => 'Senior High School',
                        'unit' => $subjectData['unit'],
                        'semester' => '1st Semester',
                    ],
                );

                if ($subjectIndex > 1) {
                    continue;
                }

                $laboratory = $laboratories[(($sectionNumber + $subjectIndex - 1) % 5) + 1];
                $startHour = 7 + (($sectionNumber - 1) * 2) + ($subjectIndex * 1);
                $endHour = $startHour + 1;

                Schedule::updateOrCreate(
                    [
                        'section_id' => $section->section_id,
                        'subject_code' => $subject->subject_code,
                    ],
                    [
                        'laboratory_id' => $laboratory->laboratory_id,
                        'instructor_id' => $instructor->instructor_id,
                        'weekdays' => $subjectIndex === 0 ? 'Monday, Wednesday' : 'Tuesday, Thursday',
                        'time_start' => sprintf('%02d:00:00', $startHour),
                        'time_end' => sprintf('%02d:00:00', $endHour),
                        'room' => $laboratory->name,
                    ],
                );
            }
        }
    }
}
