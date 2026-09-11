<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AcademicYearRollover;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicYearRolloverService
{
    public function preview(AcademicYear $source, AcademicYear $destination): array
    {
        $this->validateYears($source, $destination);
        $allEnrollments = DB::table('student_enrollments')->where('academic_year_id', $source->academic_year_id)->get();
        $transition = $this->transition($source, $allEnrollments);
        $enrollments = $allEnrollments->where('semester', $transition['current_semester'])->values();
        $items = $enrollments->map(fn ($enrollment) => [
            'student_id' => $enrollment->student_id,
            'source_student_enrollment_id' => $enrollment->student_enrollment_id,
            'source_section_id' => $enrollment->section_id,
            'year_level' => $enrollment->year_level,
            'current_status' => $enrollment->status,
            'destination_year_level' => $transition['advance_grade']
                ? ((int) $enrollment->year_level === 11 ? 12 : null)
                : (int) $enrollment->year_level,
            'recommended_decision' => match (true) {
                in_array($enrollment->status, ['dropped', 'transferred', 'inactive'], true) => 'dropped',
                $transition['advance_grade'] && (int) $enrollment->year_level === 12 => 'graduated',
                ! $transition['advance_grade'] => 'retain',
                (int) $enrollment->year_level === 11 => 'promote',
                default => 'review',
            },
        ]);
        $sourceSections = DB::table('sections')->where('academic_year_id', $source->academic_year_id)
            ->where('semester', $transition['current_semester'])
            ->get(['section_id', 'section_name', 'year_level', 'semester'])
            ->filter(fn ($section) => ! $transition['advance_grade'] || (int) $section->year_level === 11)
            ->values();

        return [
            'source' => ['id' => $source->academic_year_id, 'name' => $source->name, 'status' => $source->status],
            'destination' => ['id' => $destination->academic_year_id, 'name' => $destination->name, 'status' => $destination->status],
            'counts' => [
                'sections' => $sourceSections->count(),
                'offerings' => 0,
                'schedules' => 0,
                'students' => $items->count(),
                'promote' => $items->where('recommended_decision', 'promote')->count(),
                'retain' => $items->where('recommended_decision', 'retain')->count(),
                'archived' => $items->where('recommended_decision', 'graduated')->count(),
                'dropped' => $items->where('recommended_decision', 'dropped')->count(),
                'review' => $items->where('recommended_decision', 'review')->count(),
            ],
            'items' => $items->values(),
            'source_sections' => $sourceSections,
            'destination_sections' => DB::table('sections')->where('academic_year_id', $destination->academic_year_id)
                ->where('semester', $transition['destination_semester'])->get(['section_id', 'section_name', 'year_level', 'semester']),
            'transition' => $transition,
        ];
    }

    public function execute(AcademicYear $source, AcademicYear $destination, User $actor, array $decisions, array $sectionMappings): AcademicYearRollover
    {
        $preview = $this->preview($source, $destination);

        return DB::transaction(function () use ($source, $destination, $actor, $decisions, $sectionMappings, $preview) {
            $existing = AcademicYearRollover::query()->where('source_academic_year_id', $source->academic_year_id)
                ->where('destination_academic_year_id', $destination->academic_year_id)->lockForUpdate()->first();
            if ($existing?->status === 'completed') {
                return $existing;
            }
            $rollover = $existing ?? AcademicYearRollover::create([
                'source_academic_year_id' => $source->academic_year_id, 'destination_academic_year_id' => $destination->academic_year_id,
                'executed_by_user_id' => $actor->user_id, 'mode' => 'promote', 'status' => 'processing',
                'preview_counts' => $preview['counts'], 'started_at' => now(),
            ]);

            $sectionMap = $this->resolveSections($source, $destination, $sectionMappings);
            // Subjects, offerings, and schedules are semester-specific. Configure them
            // fresh in the destination year/semester instead of copying them forward.
            $counts = ['enrolled' => 0, 'retained' => 0, 'archived' => 0, 'skipped' => 0, 'sections' => count($sectionMap), 'offerings' => 0, 'schedules' => 0];

            foreach ($preview['items'] as $previewItem) {
                $choice = collect($decisions)->firstWhere('source_student_enrollment_id', $previewItem['source_student_enrollment_id']);
                $decision = $choice['decision'] ?? $previewItem['recommended_decision'];
                $destinationSectionId = $choice['destination_section_id'] ?? ($sectionMap[$previewItem['source_section_id']] ?? null);
                $destinationEnrollmentId = null;
                $status = 'skipped';
                $message = null;

                if (in_array($decision, ['promote', 'retain'], true)) {
                    if (! $destinationSectionId) {
                        throw ValidationException::withMessages(['rollover' => "Destination section is required for student {$previewItem['student_id']}."]);
                    }
                    $section = DB::table('sections')->where('section_id', $destinationSectionId)->where('academic_year_id', $destination->academic_year_id)->first();
                    if (! $section) {
                        throw ValidationException::withMessages(['rollover' => 'A selected destination section does not belong to the destination year.']);
                    }
                    $destinationEnrollmentId = DB::table('student_enrollments')->where('student_id', $previewItem['student_id'])
                        ->where('academic_year_id', $destination->academic_year_id)->where('semester', $preview['transition']['destination_semester'])->value('student_enrollment_id');
                    if (! $destinationEnrollmentId) {
                        $destinationEnrollmentId = DB::table('student_enrollments')->insertGetId([
                            'student_id' => $previewItem['student_id'], 'academic_year_id' => $destination->academic_year_id,
                            'section_id' => $section->section_id, 'strand_id' => $section->strand_id, 'year_level' => $section->year_level,
                            'semester' => $preview['transition']['destination_semester'], 'status' => 'enrolled', 'enrolled_at' => $destination->starts_on,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                    $status = 'completed'; $counts['enrolled']++;
                    if ($decision === 'retain') $counts['retained']++;
                } elseif ($decision === 'graduated') {
                    DB::table('students')->where('student_id', $previewItem['student_id'])->update(['status' => 'graduated', 'updated_at' => now()]);
                    $status = 'completed'; $counts['archived']++;
                } else {
                    $message = 'No destination enrollment created.'; $counts['skipped']++;
                }

                DB::table('academic_year_rollover_items')->updateOrInsert(
                    ['academic_year_rollover_id' => $rollover->academic_year_rollover_id, 'student_id' => $previewItem['student_id']],
                    ['source_student_enrollment_id' => $previewItem['source_student_enrollment_id'], 'destination_student_enrollment_id' => $destinationEnrollmentId,
                        'destination_section_id' => $destinationSectionId, 'decision' => $decision, 'status' => $status, 'message' => $message,
                        'payload' => json_encode($choice ?: $previewItem), 'created_at' => now(), 'updated_at' => now()]
                );
            }

            $rollover->update(['status' => 'completed', 'execution_counts' => $counts, 'completed_at' => now()]);
            DB::table('activity_logs')->insert(['user_id' => $actor->user_id, 'action' => 'academic_year_rollover_completed', 'table_name' => 'academic_year_rollovers',
                'description' => "Completed rollover from {$source->name} to {$destination->name}.", 'created_at' => now()]);
            return $rollover->fresh('items');
        });
    }

    private function resolveSections(AcademicYear $source, AcademicYear $destination, array $mappings): array
    {
        $map = [];
        $transition = $this->transition($source, DB::table('student_enrollments')->where('academic_year_id', $source->academic_year_id)->get());
        foreach ($mappings as $mapping) {
            $sourceSection = DB::table('sections')->where('section_id', $mapping['source_section_id'])->where('academic_year_id', $source->academic_year_id)->first();
            if (! $sourceSection) continue;
            if ($transition['advance_grade'] && (int) $sourceSection->year_level !== 11) continue;
            $destinationId = $mapping['destination_section_id'] ?? null;
            if (! $destinationId && ! empty($mapping['destination_name'])) {
                $destinationId = DB::table('sections')->where('academic_year_id', $destination->academic_year_id)->where('section_name', $mapping['destination_name'])->where('semester', $transition['destination_semester'])->value('section_id');
                $destinationId ??= DB::table('sections')->insertGetId([
                    'academic_year_id' => $destination->academic_year_id, 'strand_id' => $sourceSection->strand_id,
                    'section_name' => $mapping['destination_name'], 'year_level' => $transition['advance_grade'] ? 12 : $sourceSection->year_level,
                    'semester' => $transition['destination_semester'], 'school_year' => $destination->name, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($destinationId) {
                $destinationSection = DB::table('sections')->where('section_id', $destinationId)->where('academic_year_id', $destination->academic_year_id)->first();
                if (! $destinationSection || $destinationSection->semester !== $transition['destination_semester'] || (int) $destinationSection->year_level !== ($transition['advance_grade'] ? 12 : (int) $sourceSection->year_level)) {
                    throw ValidationException::withMessages(['rollover' => 'Every destination section must use the next semester and the automatic grade progression.']);
                }
                $map[$sourceSection->section_id] = (int) $destinationId;
            }
        }
        return $map;
    }

    private function copyOfferingsAndSchedules(AcademicYear $source, AcademicYear $destination, array $sectionMap): array
    {
        $offeringMap = [];
        $destinationSemester = $this->transition($source, DB::table('student_enrollments')->where('academic_year_id', $source->academic_year_id)->get())['destination_semester'];
        foreach (DB::table('subject_offerings')->where('academic_year_id', $source->academic_year_id)->get() as $offering) {
            if (! isset($sectionMap[$offering->section_id])) continue;
            $targetId = DB::table('subject_offerings')->where('academic_year_id', $destination->academic_year_id)->where('semester', $destinationSemester)
                ->where('section_id', $sectionMap[$offering->section_id])->where('subject_id', $offering->subject_id)->value('subject_offering_id');
            $targetId ??= DB::table('subject_offerings')->insertGetId(['academic_year_id' => $destination->academic_year_id, 'subject_id' => $offering->subject_id,
                'section_id' => $sectionMap[$offering->section_id], 'instructor_id' => null, 'semester' => $destinationSemester, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $offeringMap[$offering->subject_offering_id] = $targetId;
        }
        foreach (DB::table('schedules')->where('academic_year_id', $source->academic_year_id)->get() as $schedule) {
            if (! isset($sectionMap[$schedule->section_id]) || ! isset($offeringMap[$schedule->subject_offering_id])) continue;
            DB::table('schedules')->updateOrInsert([
                'academic_year_id' => $destination->academic_year_id, 'subject_offering_id' => $offeringMap[$schedule->subject_offering_id],
                'weekdays' => $schedule->weekdays, 'time_start' => $schedule->time_start,
            ], ['laboratory_id' => $schedule->laboratory_id, 'instructor_id' => null, 'section_id' => $sectionMap[$schedule->section_id],
                'subject_code' => $schedule->subject_code, 'semester' => $destinationSemester, 'time_end' => $schedule->time_end, 'room' => $schedule->room]);
        }
        return $offeringMap;
    }

    private function transition(AcademicYear $source, $enrollments): array
    {
        $currentSemester = $source->active_semester ?: $enrollments->pluck('semester')->filter()->first() ?: '2nd Semester';
        $firstSemester = $currentSemester === '1st Semester';

        return [
            'current_semester' => $currentSemester,
            'destination_semester' => $firstSemester ? '2nd Semester' : '1st Semester',
            'advance_grade' => ! $firstSemester,
            'description' => $firstSemester
                ? '1st Semester → 2nd Semester: students remain in the same grade.'
                : '2nd Semester → 1st Semester: Grade 11 advances to Grade 12; Grade 12 is archived as graduated.',
        ];
    }

    private function validateYears(AcademicYear $source, AcademicYear $destination): void
    {
        if ($source->is($destination) || $destination->status !== AcademicYear::STATUS_DRAFT || ! in_array($source->status, [AcademicYear::STATUS_ACTIVE, AcademicYear::STATUS_CLOSED], true)) {
            throw ValidationException::withMessages(['rollover' => 'Rollover requires a different draft destination and an active or closed source year.']);
        }
    }
}
