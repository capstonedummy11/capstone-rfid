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
    Schema::create('attendance_sessions', function (Blueprint $table) {
      $table->id('attendance_id');
      $table->string('subject_code')->nullable();
      $table->foreignId('schedule_id')->nullable()->constrained('schedules', 'scheduled_id')->nullOnDelete();
      $table->date('date')->nullable();
      $table->time('time_start')->nullable();
      $table->time('time_end')->nullable();
      $table->enum('status', ['offline', 'online', 'paused', 'attendance', 'borrowing'])->default('offline');
      $table->string('room')->nullable();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('attendance_sessions');
  }
};
