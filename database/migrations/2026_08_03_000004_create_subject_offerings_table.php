<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedBigInteger('section_id')->nullable()->change();
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

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

        DB::table('subjects')
            ->whereNotNull('section_id')
            ->orderBy('subject_id')
            ->each(function ($subject) {
                $section = DB::table('sections')->where('section_id', $subject->section_id)->first();
                if (! $section?->academic_year_id) {
                    return;
                }

                $instructorId = $subject->user_id
                    ? DB::table('instructors')->where('user_id', $subject->user_id)->value('instructor_id')
                    : null;

                DB::table('subject_offerings')->updateOrInsert(
                    [
                        'academic_year_id' => $section->academic_year_id,
                        'semester' => $subject->semester ?: $section->semester,
                        'section_id' => $section->section_id,
                        'subject_id' => $subject->subject_id,
                    ],
                    [
                        'instructor_id' => $instructorId,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_offerings');

        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedBigInteger('section_id')->nullable(false)->change();
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
