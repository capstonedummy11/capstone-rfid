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
        $enrollments = DB::table('student_enrollments')->where('academic_year_id', $source->academic_year_id)->get();
        $items = $enrollments->map(fn ($enrollment) => [
            'student_id' => $enrollment->student_id,
            'source_student_enrollment_id' => $enrollment->student_enrollment_id,
            'source_section_id' => $enrollment->section_id,
            'year_level' => $enrollment->year_level,
            'current_status' => $enrollment->status,
            'recommended_decision' => match (true) {
                in_array($enrollment->status, ['dropped', 'transferred', 'inactive'], true) => 'dropped',
                (int) $enrollment->year_level === 12 => 'graduated',
                (int) $enrollment->year_level === 11 => 'promote',
                default => 'review',
            },
        ]);

        return [
            'source' => ['id' => $source->academic_year_id, 'name' => $source->name, 'status' => $source->status],
            'destination' => ['id' => $destination->academic_year_id, 'name' => $destination->name, 'status' => $destination->status],
            'counts' => [
                'sections' => DB::table('sections')->where('academic_year_id', $source->academic_year_id)->count(),
                'offerings' => DB::table('subject_offerings')->where('academic_year_id', $source->academic_year_id)->count(),
                'schedules' => DB::table('schedules')->where('academic_year_id', $source->academic_year_id)->count(),
                'students' => $items->count(),
                'promote' => $items->where('recommended_decision', 'promote')->count(),
                'graduated' => $items->where('recommended_decision', 'graduated')->count(),
                'dropped' => $items->where('recommended_decision', 'dropped')->count(),
                'review' => $items->where('recommended_decision', 'review')->count(),
            ],
            'items' => $items->values(),
            'source_sections' => DB::table('sections')->where('academic_year_id', $source->academic_year_id)->get(['section_id', 'section_name', 'year_level', 'semester']),
            'destination_sections' => DB::table('sections')->where('academic_year_id', $destination->academic_year_id)->get(['section_id', 'section_name', 'year_level', 'semester']),
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
            $offeringMap = $this->copyOfferingsAndSchedules($source, $destination, $sectionMap);
            $counts = ['enrolled' => 0, 'graduated' => 0, 'skipped' => 0, 'sections' => count($sectionMap), 'offerings' => count($offeringMap)];

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
                        ->where('academic_year_id', $destination->academic_year_id)->where('semester', $section->semester)->value('student_enrollment_id');
                    if (! $destinationEnrollmentId) {
                        $destinationEnrollmentId = DB::table('student_enrollments')->insertGetId([
                            'student_id' => $previewItem['student_id'], 'academic_year_id' => $destination->academic_year_id,
                            'section_id' => $section->section_id, 'strand_id' => $section->strand_id, 'year_level' => $section->year_level,
                            'semester' => $section->semester, 'status' => 'enrolled', 'enrolled_at' => $destination->starts_on,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                    $status = 'completed'; $counts['enrolled']++;
                } elseif ($decision === 'graduated') {
                    $status = 'completed'; $counts['graduated']++;
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
        foreach ($mappings as $mapping) {
            $sourceSection = DB::table('sections')->where('section_id', $mapping['source_section_id'])->where('academic_year_id', $source->academic_year_id)->first();
            if (! $sourceSection) continue;
            $destinationId = $mapping['destination_section_id'] ?? null;
            if (! $destinationId && ! empty($mapping['destination_name'])) {
                $destinationId = DB::table('sections')->where('academic_year_id', $destination->academic_year_id)->where('section_name', $mapping['destination_name'])->where('semester', $sourceSection->semester)->value('section_id');
                $destinationId ??= DB::table('sections')->insertGetId([
                    'academic_year_id' => $destination->academic_year_id, 'strand_id' => $sourceSection->strand_id,
                    'section_name' => $mapping['destination_name'], 'year_level' => $mapping['destination_year_level'] ?? min(12, $sourceSection->year_level + 1),
                    'semester' => $sourceSection->semester, 'school_year' => $destination->name, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($destinationId) $map[$sourceSection->section_id] = (int) $destinationId;
        }
        return $map;
    }

    private function copyOfferingsAndSchedules(AcademicYear $source, AcademicYear $destination, array $sectionMap): array
    {
        $offeringMap = [];
        foreach (DB::table('subject_offerings')->where('academic_year_id', $source->academic_year_id)->get() as $offering) {
            if (! isset($sectionMap[$offering->section_id])) continue;
            $targetId = DB::table('subject_offerings')->where('academic_year_id', $destination->academic_year_id)->where('semester', $offering->semester)
                ->where('section_id', $sectionMap[$offering->section_id])->where('subject_id', $offering->subject_id)->value('subject_offering_id');
            $targetId ??= DB::table('subject_offerings')->insertGetId(['academic_year_id' => $destination->academic_year_id, 'subject_id' => $offering->subject_id,
                'section_id' => $sectionMap[$offering->section_id], 'instructor_id' => null, 'semester' => $offering->semester, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $offeringMap[$offering->subject_offering_id] = $targetId;
        }
        foreach (DB::table('schedules')->where('academic_year_id', $source->academic_year_id)->get() as $schedule) {
            if (! isset($sectionMap[$schedule->section_id]) || ! isset($offeringMap[$schedule->subject_offering_id])) continue;
            DB::table('schedules')->updateOrInsert([
                'academic_year_id' => $destination->academic_year_id, 'subject_offering_id' => $offeringMap[$schedule->subject_offering_id],
                'weekdays' => $schedule->weekdays, 'time_start' => $schedule->time_start,
            ], ['laboratory_id' => $schedule->laboratory_id, 'instructor_id' => null, 'section_id' => $sectionMap[$schedule->section_id],
                'subject_code' => $schedule->subject_code, 'semester' => $schedule->semester, 'time_end' => $schedule->time_end, 'room' => $schedule->room]);
        }
        return $offeringMap;
    }

    private function validateYears(AcademicYear $source, AcademicYear $destination): void
    {
        if ($source->is($destination) || $destination->status !== AcademicYear::STATUS_DRAFT || ! in_array($source->status, [AcademicYear::STATUS_ACTIVE, AcademicYear::STATUS_CLOSED], true)) {
            throw ValidationException::withMessages(['rollover' => 'Rollover requires a different draft destination and an active or closed source year.']);
        }
    }
}
