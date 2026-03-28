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
    if (!Schema::hasColumn('subjects', 'year_level')) {
      Schema::table('subjects', function (Blueprint $table) {
        $table->unsignedTinyInteger('year_level')->nullable()->after('subject_code');
      });
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    if (Schema::hasColumn('subjects', 'year_level')) {
      Schema::table('subjects', function (Blueprint $table) {
        $table->dropColumn('year_level');
      });
    }
  }
};
