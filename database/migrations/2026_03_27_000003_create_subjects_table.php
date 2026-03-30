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
    Schema::create('subjects', function (Blueprint $table) {
      $table->id('subject_id');
      $table->foreignId('section_id')->constrained('sections', 'section_id')->cascadeOnDelete();
      $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
      $table->string('subject_name');
      $table->string('subject_code');
      $table->unsignedTinyInteger('year_level')->nullable();
      $table->string('department')->nullable();
      $table->unsignedTinyInteger('unit')->default(3);
      $table->string('semester')->nullable();
      $table->timestamps();

      $table->unique(['subject_code', 'section_id']);
      $table->index(['user_id', 'section_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('subjects');
  }
};
