<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('scheduled_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('subject_offering_id')->nullable()->after('academic_year_id')->constrained('subject_offerings', 'subject_offering_id')->restrictOnDelete();
            $table->string('semester', 50)->nullable()->after('subject_code');
            $table->index(['academic_year_id', 'weekdays', 'time_start'], 'schedule_year_time_index');
        });

        DB::table('schedules')->orderBy('scheduled_id')->each(function ($schedule) {
            $section = DB::table('sections')->where('section_id', $schedule->section_id)->first();
            if (! $section?->academic_year_id) {
                return;
            }

            $subjectId = DB::table('subjects')->where('subject_code', $schedule->subject_code)->value('subject_id');
            $offeringQuery = DB::table('subject_offerings')
                ->where('academic_year_id', $section->academic_year_id)
                ->where('section_id', $section->section_id)
                ->where('subject_id', $subjectId ?: 0);

            $offering = $schedule->instructor_id
                ? (clone $offeringQuery)->where('instructor_id', $schedule->instructor_id)->first()
                : null;
            $offering ??= $offeringQuery->orderBy('subject_offering_id')->first();

            DB::table('schedules')->where('scheduled_id', $schedule->scheduled_id)->update([
                'academic_year_id' => $section->academic_year_id,
                'subject_offering_id' => $offering?->subject_offering_id,
                'semester' => $offering?->semester ?: $section->semester,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedule_year_time_index');
            $table->dropConstrainedForeignId('subject_offering_id');
            $table->dropConstrainedForeignId('academic_year_id');
            $table->dropColumn('semester');
        });
    }
};
