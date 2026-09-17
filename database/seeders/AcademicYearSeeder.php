<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    /**
     * Seed the academic year used by the existing demo records.
     */
    public function run(): void
    {
        $name = env('DEMO_SCHOOL_YEAR', '2026-2027');
        [$startYear, $endYear] = array_map('intval', explode('-', $name));

        AcademicYear::query()->updateOrCreate(
            ['name' => $name],
            [
                'starts_on' => env('DEMO_ACADEMIC_YEAR_START', "{$startYear}-06-01"),
                'ends_on' => env('DEMO_ACADEMIC_YEAR_END', "{$endYear}-03-31"),
                'status' => AcademicYear::STATUS_ACTIVE,
                'active_semester' => '1st Semester',
                'activated_at' => now(),
            ],
        );
    }
}
