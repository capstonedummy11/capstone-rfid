<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id('attendance_id');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('student_id')->constrained('students', 'student_id');
            $table->foreignId('schedule_id')->nullable()->constrained('schedules', 'scheduled_id')->nullOnDelete();
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('subject_offering_id')->nullable();
            $table->unsignedBigInteger('student_enrollment_id')->nullable();
            $table->date('date');
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->string('check_in_status')->nullable();
            $table->string('room_status')->default('outside');
            $table->unsignedInteger('total_taps')->default(0);
            $table->text('remarks')->nullable();
            $table->string('status', 50)->default('pending');
            $table->string('subject_code')->nullable();
            $table->string('room')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'student_id', 'date'], 'attendance_year_student_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
