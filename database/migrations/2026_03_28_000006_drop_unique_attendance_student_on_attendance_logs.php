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
    Schema::table('attendance_logs', function (Blueprint $table) {
      $table->dropForeign(['student_id']);
      $table->dropForeign(['attendance_id']);
      $table->dropUnique('attendance_logs_attendance_id_student_id_unique');
      $table->index('attendance_id', 'attendance_logs_attendance_id_index');
      $table->index('student_id', 'attendance_logs_student_id_index');
      $table->foreign('attendance_id')->references('attendance_id')->on('attendance_sessions')->cascadeOnDelete();
      $table->foreign('student_id')->references('student_id')->on('students')->cascadeOnDelete();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('attendance_logs', function (Blueprint $table) {
      $table->dropForeign(['student_id']);
      $table->dropForeign(['attendance_id']);
      $table->dropIndex('attendance_logs_student_id_index');
      $table->dropIndex('attendance_logs_attendance_id_index');
      $table->unique(['attendance_id', 'student_id']);
      $table->foreign('attendance_id')->references('attendance_id')->on('attendance_sessions')->cascadeOnDelete();
      $table->foreign('student_id')->references('student_id')->on('students')->cascadeOnDelete();
    });
  }
};
