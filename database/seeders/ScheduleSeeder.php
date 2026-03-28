<?php

namespace Database\Seeders;

use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $section = Section::query()->first();

        if (! $section) {
            return;
        }

        $subjectCodes = Subject::query()->pluck('subject_code')->all();

        foreach ($subjectCodes as $index => $subjectCode) {
            Schedule::updateOrCreate(
                [
                    'section_id' => $section->section_id,
                    'subject_code' => $subjectCode,
                    'weekdays' => $index === 0 ? 'Mon/Wed' : 'Tue/Thu',
                ],
                [
                    'time_start' => $index === 0 ? '08:00:00' : '10:00:00',
                    'time_end' => $index === 0 ? '09:30:00' : '11:30:00',
                    'room' => 'Lab 1',
                    'timestamp' => Carbon::now(),
                ],
            );
        }
    }
}