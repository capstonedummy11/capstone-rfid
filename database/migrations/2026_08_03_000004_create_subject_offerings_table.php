<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_offerings', function (Blueprint $table) {
            $table->id('subject_offering_id');
            $table->foreignId('academic_year_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects', 'subject_id')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections', 'section_id')->restrictOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained('instructors', 'instructor_id')->nullOnDelete();
            $table->string('semester', 50);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(
                ['academic_year_id', 'semester', 'section_id', 'subject_id'],
                'subject_offering_period_unique',
            );
            $table->index(['instructor_id', 'academic_year_id', 'status'], 'subject_offering_instructor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_offerings');
    }
};
