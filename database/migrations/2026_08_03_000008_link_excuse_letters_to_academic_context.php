<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_excuse_letters', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('student_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->after('academic_year_id')->constrained('student_enrollments', 'student_enrollment_id')->restrictOnDelete();
            $table->index(['academic_year_id', 'student_id'], 'excuse_letter_year_student_index');
        });

        DB::table('student_excuse_letters')->orderBy('student_excuse_letter_id')->each(function ($letter) {
            $enrollment = DB::table('student_enrollments')
                ->join('academic_years', 'academic_years.academic_year_id', '=', 'student_enrollments.academic_year_id')
                ->where('student_enrollments.student_id', $letter->student_id)
                ->whereDate('academic_years.starts_on', '<=', $letter->from_date)
                ->whereDate('academic_years.ends_on', '>=', $letter->from_date)
                ->select('student_enrollments.student_enrollment_id', 'student_enrollments.academic_year_id')
                ->first();
            if ($enrollment) {
                DB::table('student_excuse_letters')->where('student_excuse_letter_id', $letter->student_excuse_letter_id)->update([
                    'academic_year_id' => $enrollment->academic_year_id,
                    'student_enrollment_id' => $enrollment->student_enrollment_id,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_excuse_letters', function (Blueprint $table) {
            $table->dropIndex('excuse_letter_year_student_index');
            $table->dropConstrainedForeignId('student_enrollment_id');
            $table->dropConstrainedForeignId('academic_year_id');
        });
    }
};
