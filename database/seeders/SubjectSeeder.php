<?php

namespace Database\Seeders;

use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $section = Section::query()->first();
        $teacher = User::query()->whereIn('role', ['admin', 'instructor', 'teacher'])->first();

        $subjects = [
            [
                'subject_name' => 'Introduction to RFID Systems',
                'subject_code' => 'RFID101',
                'subject_description' => 'Foundations of RFID operations and attendance workflows.',
                'department' => 'Information Technology',
                'unit' => 3,
                'semester' => '1st Semester',
            ],
            [
                'subject_name' => 'Inventory Management',
                'subject_code' => 'INV201',
                'subject_description' => 'Tracking, borrowing, and auditing inventory records.',
                'department' => 'Information Technology',
                'unit' => 3,
                'semester' => '2nd Semester',
            ],
        ];

        foreach ($subjects as $subject) {
            Subject::updateOrCreate(
                ['subject_code' => $subject['subject_code']],
                $subject + [
                    'section_id' => $section?->section_id,
                    'user_id' => $teacher?->user_id,
                ],
            );
        }
    }
}