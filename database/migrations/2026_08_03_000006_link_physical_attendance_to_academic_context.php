<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('schedule_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('subject_offering_id')->nullable()->after('academic_year_id')->constrained('subject_offerings', 'subject_offering_id')->restrictOnDelete();
            $table->index(['academic_year_id', 'date'], 'attendance_session_year_date_index');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('schedule_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('subject_offering_id')->nullable()->after('academic_year_id')->constrained('subject_offerings', 'subject_offering_id')->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->after('subject_offering_id')->constrained('student_enrollments', 'student_enrollment_id')->restrictOnDelete();
            $table->index(['academic_year_id', 'student_id', 'date'], 'attendance_year_student_date_index');
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('schedule_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('subject_offering_id')->nullable()->after('academic_year_id')->constrained('subject_offerings', 'subject_offering_id')->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->after('subject_offering_id')->constrained('student_enrollments', 'student_enrollment_id')->restrictOnDelete();
            $table->index(['academic_year_id', 'student_id'], 'attendance_log_year_student_index');
        });

        Schema::table('rfid_panel_sessions', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('schedule_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('subject_offering_id')->nullable()->after('academic_year_id')->constrained('subject_offerings', 'subject_offering_id')->restrictOnDelete();
        });

        $this->backfillScheduleContext('attendance_sessions', 'attendance_id');
        $this->backfillScheduleContext('rfid_panel_sessions', 'panel_session_id');

        DB::table('attendances')->orderBy('attendance_id')->each(function ($attendance) {
            $context = $this->contextFor($attendance->schedule_id, $attendance->student_id);
            DB::table('attendances')->where('attendance_id', $attendance->attendance_id)->update($context);
        });

        DB::table('attendance_logs')->orderBy('id')->each(function ($log) {
            $attendance = $log->main_attendance_id
                ? DB::table('attendances')->where('attendance_id', $log->main_attendance_id)->first()
                : null;
            $context = $attendance
                ? array_filter([
                    'academic_year_id' => $attendance->academic_year_id,
                    'subject_offering_id' => $attendance->subject_offering_id,
                    'student_enrollment_id' => $attendance->student_enrollment_id,
                ], fn ($value) => $value !== null)
                : $this->contextFor($log->schedule_id, $log->student_id);
            if ($context) {
                DB::table('attendance_logs')->where('id', $log->id)->update($context);
            }
        });
    }

    private function backfillScheduleContext(string $table, string $key): void
    {
        DB::table($table)->orderBy($key)->each(function ($record) use ($table, $key) {
            $context = $this->contextFor($record->schedule_id, null);
            if ($context) {
                DB::table($table)->where($key, $record->{$key})->update($context);
            }
        });
    }

    private function contextFor(?int $scheduleId, ?int $studentId): array
    {
        if (! $scheduleId || ! ($schedule = DB::table('schedules')->where('scheduled_id', $scheduleId)->first())) {
            return [];
        }

        $context = array_filter([
            'academic_year_id' => $schedule->academic_year_id,
            'subject_offering_id' => $schedule->subject_offering_id,
        ], fn ($value) => $value !== null);

        if ($studentId && $schedule->academic_year_id) {
            $enrollments = DB::table('student_enrollments')
                ->where('student_id', $studentId)
                ->where('academic_year_id', $schedule->academic_year_id);
            $enrollment = $schedule->semester
                ? (clone $enrollments)->where('semester', $schedule->semester)->first()
                : null;
            $enrollment ??= $enrollments->orderBy('student_enrollment_id')->first();
            $context['student_enrollment_id'] = $enrollment?->student_enrollment_id;
        }

        return $context;
    }

    public function down(): void
    {
        Schema::table('rfid_panel_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_offering_id');
            $table->dropConstrainedForeignId('academic_year_id');
        });
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex('attendance_log_year_student_index');
            $table->dropConstrainedForeignId('student_enrollment_id');
            $table->dropConstrainedForeignId('subject_offering_id');
            $table->dropConstrainedForeignId('academic_year_id');
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendance_year_student_date_index');
            $table->dropConstrainedForeignId('student_enrollment_id');
            $table->dropConstrainedForeignId('subject_offering_id');
            $table->dropConstrainedForeignId('academic_year_id');
        });
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex('attendance_session_year_date_index');
            $table->dropConstrainedForeignId('subject_offering_id');
            $table->dropConstrainedForeignId('academic_year_id');
        });
    }
};
