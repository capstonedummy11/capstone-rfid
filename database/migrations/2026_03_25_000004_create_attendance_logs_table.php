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
    Schema::create('attendance_logs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('attendance_id')->constrained('attendance_sessions', 'attendance_id')->cascadeOnDelete();
      $table->foreignId('main_attendance_id')->nullable()->constrained('attendances', 'attendance_id')->nullOnDelete();
      $table->foreignId('student_id')->nullable()->constrained('students', 'student_id')->nullOnDelete();
      $table->foreignId('schedule_id')->nullable()->constrained('schedules', 'scheduled_id')->nullOnDelete();
      $table->unsignedBigInteger('academic_year_id')->nullable();
      $table->unsignedBigInteger('subject_offering_id')->nullable();
      $table->unsignedBigInteger('student_enrollment_id')->nullable();
      $table->time('time_in')->nullable();
      $table->time('time_out')->nullable();
      $table->string('status')->nullable();
      $table->string('verification_method')->nullable();
      $table->string('time_in_face_path')->nullable();
      $table->string('time_out_face_path')->nullable();
      $table->boolean('is_late')->default(false);
      $table->string('completion_reason')->nullable();
      $table->dateTime('tap_datetime')->nullable();
      $table->string('tap_type')->nullable();
      $table->unsignedInteger('tap_sequence_number')->default(1);
      $table->string('device_scanner_id')->nullable();
      $table->string('location')->nullable();
      $table->string('validation_result')->default('valid');
      $table->text('remarks')->nullable();
      $table->timestamps();

      $table->index(['academic_year_id', 'student_id'], 'attendance_log_year_student_index');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('attendance_logs');
  }
};
