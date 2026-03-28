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
      $table->foreignId('student_id')->nullable()->after('attendance_id')->constrained('students', 'student_id')->cascadeOnDelete();
      $table->unique(['attendance_id', 'student_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('attendance_logs', function (Blueprint $table) {
      $table->dropUnique('attendance_logs_attendance_id_student_id_unique');
      $table->dropConstrainedForeignId('student_id');
    });
  }
};
