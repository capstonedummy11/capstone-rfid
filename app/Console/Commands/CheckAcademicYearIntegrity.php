<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckAcademicYearIntegrity extends Command
{
    protected $signature = 'academic-years:check-integrity {--json : Emit machine-readable JSON}';
    protected $description = 'Reconcile academic-year foreign keys, enrollment compatibility, and active-year invariants.';

    public function handle(): int
    {
        $checks = [
            $this->check('multiple_active_years', max(0, DB::table('academic_years')->where('status', 'active')->count() - 1), 'At most one academic year may be active.'),
            $this->check('students_without_enrollment', DB::table('students')->whereNull('deleted_at')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('student_enrollments')->whereColumn('student_enrollments.student_id', 'students.student_id'))->count(), 'Every non-deleted student needs an enrollment.'),
            $this->check('attendance_year_enrollment_mismatch', DB::table('attendances')->whereNotNull('academic_year_id')->whereNotNull('student_enrollment_id')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('student_enrollments')->whereColumn('student_enrollments.student_enrollment_id', 'attendances.student_enrollment_id')->whereColumn('student_enrollments.student_id', 'attendances.student_id')->whereColumn('student_enrollments.academic_year_id', 'attendances.academic_year_id'))->count(), 'Attendance context must match its student enrollment.'),
            $this->check('online_attendance_outside_roster', DB::table('online_class_attendances')->join('online_classes', 'online_classes.online_class_id', '=', 'online_class_attendances.online_class_id')->whereNotNull('online_classes.academic_year_id')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('student_enrollments')->whereColumn('student_enrollments.student_id', 'online_class_attendances.student_id')->whereColumn('student_enrollments.academic_year_id', 'online_classes.academic_year_id')->whereColumn('student_enrollments.section_id', 'online_classes.section_id'))->count(), 'Online attendance must belong to the class roster.'),
        ];

        foreach ($this->orphanChecks() as $check) $checks[] = $check;
        $errors = collect($checks)->where('count', '>', 0)->values();
        $result = ['ok' => $errors->isEmpty(), 'checked_at' => now()->toIso8601String(), 'checks' => $checks, 'error_count' => $errors->sum('count')];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
        } else {
            $this->table(['Check', 'Count', 'Result'], collect($checks)->map(fn ($check) => [$check['name'], $check['count'], $check['count'] ? 'FAIL' : 'PASS']));
            $errors->isEmpty() ? $this->info('Academic-year integrity checks passed.') : $this->error("Academic-year integrity checks found {$result['error_count']} issue(s).");
        }

        return $errors->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    private function orphanChecks(): array
    {
        $relations = [
            ['sections', 'academic_year_id', 'academic_years', 'academic_year_id'],
            ['student_enrollments', 'academic_year_id', 'academic_years', 'academic_year_id'],
            ['student_enrollments', 'student_id', 'students', 'student_id'],
            ['subject_offerings', 'academic_year_id', 'academic_years', 'academic_year_id'],
            ['schedules', 'subject_offering_id', 'subject_offerings', 'subject_offering_id'],
            ['attendances', 'student_enrollment_id', 'student_enrollments', 'student_enrollment_id'],
            ['online_classes', 'subject_offering_id', 'subject_offerings', 'subject_offering_id'],
            ['online_class_attendances', 'student_enrollment_id', 'student_enrollments', 'student_enrollment_id'],
            ['student_excuse_letters', 'student_enrollment_id', 'student_enrollments', 'student_enrollment_id'],
        ];

        return collect($relations)->filter(fn ($relation) => Schema::hasTable($relation[0]) && Schema::hasColumn($relation[0], $relation[1]))
            ->map(function ($relation) {
                [$table, $foreignKey, $parent, $parentKey] = $relation;
                $count = DB::table($table)->whereNotNull("{$table}.{$foreignKey}")->whereNotExists(fn ($query) => $query->selectRaw('1')->from($parent)->whereColumn("{$parent}.{$parentKey}", "{$table}.{$foreignKey}"))->count();
                return $this->check("orphan_{$table}_{$foreignKey}", $count, "{$table}.{$foreignKey} must reference {$parent}.{$parentKey}.");
            })->values()->all();
    }

    private function check(string $name, int $count, string $description): array
    {
        return compact('name', 'count', 'description');
    }
}
