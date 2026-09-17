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
    Schema::create('schedules', function (Blueprint $table) {
      $table->id('scheduled_id');
      $table->unsignedBigInteger('academic_year_id')->nullable();
      $table->unsignedBigInteger('subject_offering_id')->nullable();
      $table->foreignId('section_id')->constrained('sections', 'section_id');
      $table->string('subject_code');
      $table->string('semester', 50)->nullable();
      $table->string('weekdays');
      $table->time('time_start');
      $table->time('time_end');
      $table->string('room');
      $table->unsignedBigInteger('laboratory_id')->nullable();
      $table->unsignedBigInteger('instructor_id')->nullable();
      $table->timestamps();

      $table->index(['academic_year_id', 'weekdays', 'time_start'], 'schedule_year_time_index');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('schedules');
  }
};
