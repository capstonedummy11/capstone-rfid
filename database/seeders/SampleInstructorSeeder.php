<?php

namespace Database\Seeders;

use App\Models\Instructor;
use App\Models\Laboratory;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Strand;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleInstructorSeeder extends Seeder
{
    private const SAMPLE_INSTRUCTOR_RFID = 'RFID-INSTRUCTOR-SAMPLE';

    /**
     * Seed a complete sample instructor account with class data.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'instructor@sample.com'],
            [
                'name' => 'Sample Instructor',
                'email' => 'instructor@sample.com',
                'password' => Hash::make('sample'),
                'role' => 'instructor',
                'rfid_tag' => self::SAMPLE_INSTRUCTOR_RFID,
            ],
        );

        $strand = Strand::updateOrCreate(
            ['strand_code' => 'SAMPLE-ICT'],
            [
                'strand_name' => 'Sample ICT Strand',
                'department' => 'Information Technology',
                'status' => 'active',
            ],
        );

        $section = Section::updateOrCreate(
            [
                'strand_id' => $strand->strand_id,
                'section_name' => 'Sample Section A',
                'year_level' => 1,
                'semester' => '1st Semester',
                'school_year' => '2026-2027',
            ],
            [
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

        $laboratory = Laboratory::updateOrCreate(
            ['name' => 'Sample Computer Laboratory'],
            [
                'description' => 'Sample laboratory for instructor schedules.',
                'location' => 'Main Building',
                'status' => 'active',
            ],
        );

        $subject = Subject::updateOrCreate(
            [
                'subject_code' => 'ICT101',
                'section_id' => $section->section_id,
            ],
            [
                'user_id' => $user->user_id,
                'subject_name' => 'Introduction to ICT',
                'department' => 'Information Technology',
                'unit' => 3,
                'semester' => '1st Semester',
            ],
        );

        Schedule::updateOrCreate(
            [
                'section_id' => $section->section_id,
                'subject_code' => $subject->subject_code,
            ],
            [
                'laboratory_id' => $laboratory->laboratory_id,
                'instructor_id' => $instructor->instructor_id,
                'weekdays' => 'Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday',
                'time_start' => '00:00:00',
                'time_end' => '23:59:59',
                'room' => $laboratory->name,
            ],
        );
    }
}
