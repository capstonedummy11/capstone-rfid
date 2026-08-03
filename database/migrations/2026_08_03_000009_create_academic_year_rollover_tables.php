<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_year_rollovers', function (Blueprint $table) {
            $table->id('academic_year_rollover_id');
            $table->foreignId('source_academic_year_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('destination_academic_year_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('executed_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('mode')->default('promote');
            $table->string('status')->default('processing');
            $table->json('preview_counts')->nullable();
            $table->json('execution_counts')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['source_academic_year_id', 'destination_academic_year_id'], 'rollover_source_destination_unique');
        });

        Schema::create('academic_year_rollover_items', function (Blueprint $table) {
            $table->id('academic_year_rollover_item_id');
            $table->foreignId('academic_year_rollover_id')->constrained('academic_year_rollovers', 'academic_year_rollover_id')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students', 'student_id')->restrictOnDelete();
            $table->foreignId('source_student_enrollment_id')->nullable()->constrained('student_enrollments', 'student_enrollment_id')->restrictOnDelete();
            $table->foreignId('destination_student_enrollment_id')->nullable()->constrained('student_enrollments', 'student_enrollment_id')->restrictOnDelete();
            $table->foreignId('destination_section_id')->nullable()->constrained('sections', 'section_id')->restrictOnDelete();
            $table->string('decision');
            $table->string('status')->default('pending');
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['academic_year_rollover_id', 'student_id'], 'rollover_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_year_rollover_items');
        Schema::dropIfExists('academic_year_rollovers');
    }
};
