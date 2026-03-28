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
    Schema::create('rfid_panel_sessions', function (Blueprint $table) {
      $table->id('panel_session_id');
      $table->string('panel_id')->nullable()->index();
      $table->string('room');
      $table->enum('status', ['offline', 'online', 'paused', 'attendance', 'borrowing'])->default('offline');
      $table->string('subject_code')->nullable();
      $table->foreignId('schedule_id')->nullable()->constrained('schedules', 'scheduled_id')->nullOnDelete();
      $table->foreignId('opened_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
      $table->boolean('is_listening')->default(false);
      $table->timestamp('listening_started_at')->nullable();
      $table->timestamp('paused_at')->nullable();
      $table->timestamp('ended_at')->nullable();
      $table->json('meta')->nullable();
      $table->timestamps();

      $table->index(['status', 'room']);
      $table->index('subject_code');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('rfid_panel_sessions');
  }
};
