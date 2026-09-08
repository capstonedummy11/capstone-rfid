<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use App\Models\Students;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class StudentEnrollmentService
{
    public function syncLegacyPlacement(Students $student): StudentEnrollment
    {
        $academicYear = $this->yearForLabel((string) $student->school_year);

        return StudentEnrollment::query()->updateOrCreate(
            [
                'student_id' => $student->student_id,
                'academic_year_id' => $academicYear->academic_year_id,
                'semester' => $student->semester,
            ],
            [
                'section_id' => $student->section_id,
                'strand_id' => $student->strand_id,
                'year_level' => $student->year_level,
                'status' => $this->enrollmentStatus((string) $student->status),
            ],
        );
    }

    public function syncPlacement(Students $student, array $placement): StudentEnrollment
    {
        $academicYear = $this->yearForLabel((string) $placement['school_year']);

        return StudentEnrollment::query()->updateOrCreate(
            ['student_id' => $student->student_id, 'academic_year_id' => $academicYear->academic_year_id, 'semester' => $placement['semester']],
            ['section_id' => $placement['section_id'], 'strand_id' => $placement['strand_id'], 'year_level' => $placement['year_level'],
                'status' => $this->enrollmentStatus((string) ($placement['status'] ?? $student->status))]
        );
    }

    public function yearForLabel(string $label): AcademicYear
    {
        $label = trim($label);
        if (preg_match('/^(\d{4})-(\d{4})$/', $label, $matches) !== 1 || (int) $matches[2] !== (int) $matches[1] + 1) {
            throw ValidationException::withMessages([
                'school_year' => 'School year must contain consecutive years, for example 2026-2027.',
            ]);
        }

        $start = (int) $matches[1];
        $end = (int) $matches[2];

        return AcademicYear::query()->firstOrCreate(
            ['name' => $label],
            [
                'starts_on' => Carbon::create($start, 6, 1)->toDateString(),
                'ends_on' => Carbon::create($end, 3, 31)->toDateString(),
                'status' => AcademicYear::STATUS_DRAFT,
            ],
        );
    }

    private function enrollmentStatus(string $legacyStatus): string
    {
        return match ($legacyStatus) {
            'graduated' => 'graduated',
            'dropped' => 'dropped',
            'inactive' => 'inactive',
            default => 'enrolled',
        };
    }
}
