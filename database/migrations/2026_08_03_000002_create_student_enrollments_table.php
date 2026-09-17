<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id('student_enrollment_id');
            $table->foreignId('student_id')->constrained('students', 'student_id')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years', 'academic_year_id')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections', 'section_id')->restrictOnDelete();
            $table->foreignId('strand_id')->constrained('strands', 'strand_id')->restrictOnDelete();
            $table->unsignedTinyInteger('year_level');
            $table->string('semester', 50);
            $table->string('status', 30)->default('enrolled');
            $table->date('enrolled_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id', 'semester'], 'student_enrollment_period_unique');
            $table->index(['academic_year_id', 'section_id', 'status'], 'student_enrollment_roster_index');
        });

        $labels = DB::table('sections')->whereNotNull('school_year')->pluck('school_year')
            ->merge(DB::table('students')->whereNotNull('school_year')->pluck('school_year'))
            ->map(fn ($label) => trim((string) $label))
            ->filter(fn ($label) => preg_match('/^(\d{4})-(\d{4})$/', $label) === 1)
            ->unique()
            ->values();

        foreach ($labels as $label) {
            [$start, $end] = array_map('intval', explode('-', $label));
            if ($end !== $start + 1) {
                continue;
            }

            if (! DB::table('academic_years')->where('name', $label)->exists()) {
                DB::table('academic_years')->insert([
                    'name' => $label,
                    'starts_on' => Carbon::create($start, 6, 1)->toDateString(),
                    'ends_on' => Carbon::create($end, 3, 31)->toDateString(),
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('students')
            ->whereNull('deleted_at')
            ->orderBy('student_id')
            ->each(function ($student) {
                $yearId = DB::table('academic_years')->where('name', trim((string) $student->school_year))->value('academic_year_id');
                if (! $yearId || ! $student->section_id || ! $student->strand_id || ! $student->semester) {
                    return;
                }

                DB::table('student_enrollments')->updateOrInsert(
                    [
                        'student_id' => $student->student_id,
                        'academic_year_id' => $yearId,
                        'semester' => $student->semester,
                    ],
                    [
                        'section_id' => $student->section_id,
                        'strand_id' => $student->strand_id,
                        'year_level' => $student->year_level,
                        'status' => match ($student->status) {
                            'graduated' => 'graduated',
                            'dropped' => 'dropped',
                            'inactive' => 'inactive',
                            default => 'enrolled',
                        },
                        'enrolled_at' => null,
                        'ended_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
