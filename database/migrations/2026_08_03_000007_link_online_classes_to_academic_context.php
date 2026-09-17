<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_classes', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('schedule_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('subject_offering_id')->nullable()->after('academic_year_id')->constrained('subject_offerings', 'subject_offering_id')->restrictOnDelete();
            $table->index(['academic_year_id', 'scheduled_date'], 'online_class_year_date_index');
        });
        Schema::table('online_class_attendances', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('student_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('subject_offering_id')->nullable()->after('academic_year_id')->constrained('subject_offerings', 'subject_offering_id')->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->after('subject_offering_id')->constrained('student_enrollments', 'student_enrollment_id')->restrictOnDelete();
        });
        Schema::table('online_class_notifications', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('student_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('subject_offering_id')->nullable()->after('academic_year_id')->constrained('subject_offerings', 'subject_offering_id')->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->after('subject_offering_id')->constrained('student_enrollments', 'student_enrollment_id')->restrictOnDelete();
        });

        DB::table('online_classes')->orderBy('online_class_id')->each(function ($class) {
            $schedule = DB::table('schedules')->where('scheduled_id', $class->schedule_id)->first();
            DB::table('online_classes')->where('online_class_id', $class->online_class_id)->update([
                'academic_year_id' => $schedule?->academic_year_id,
                'subject_offering_id' => $schedule?->subject_offering_id,
            ]);
        });

        foreach (['online_class_attendances' => 'online_class_attendance_id', 'online_class_notifications' => 'online_class_notification_id'] as $table => $key) {
            DB::table($table)->orderBy($key)->each(function ($row) use ($table, $key) {
                $class = DB::table('online_classes')->where('online_class_id', $row->online_class_id)->first();
                $enrollment = $class?->academic_year_id
                    ? DB::table('student_enrollments')->where('student_id', $row->student_id)
                        ->where('academic_year_id', $class->academic_year_id)->where('section_id', $class->section_id)
                        ->orderBy('student_enrollment_id')->first()
                    : null;
                DB::table($table)->where($key, $row->{$key})->update([
                    'academic_year_id' => $class?->academic_year_id,
                    'subject_offering_id' => $class?->subject_offering_id,
                    'student_enrollment_id' => $enrollment?->student_enrollment_id,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('online_class_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_enrollment_id'); $table->dropConstrainedForeignId('subject_offering_id'); $table->dropConstrainedForeignId('academic_year_id');
        });
        Schema::table('online_class_attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_enrollment_id'); $table->dropConstrainedForeignId('subject_offering_id'); $table->dropConstrainedForeignId('academic_year_id');
        });
        Schema::table('online_classes', function (Blueprint $table) {
            $table->dropIndex('online_class_year_date_index'); $table->dropConstrainedForeignId('subject_offering_id'); $table->dropConstrainedForeignId('academic_year_id');
        });
    }
};
