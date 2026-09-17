<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('section_id')->nullable()->change();
            $table->unsignedBigInteger('strand_id')->nullable()->change();
            $table->integer('year_level')->nullable()->change();
            $table->string('semester')->nullable()->change();
            $table->string('school_year')->nullable()->change();
        });
        Schema::create('legacy_academic_fallback_events', function (Blueprint $table) {
            $table->id('legacy_academic_fallback_event_id');
            $table->string('context')->unique();
            $table->unsignedBigInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->json('last_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_academic_fallback_events');
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('section_id')->nullable(false)->change();
            $table->unsignedBigInteger('strand_id')->nullable(false)->change();
            $table->integer('year_level')->nullable(false)->change();
            $table->string('semester')->nullable(false)->change();
            $table->string('school_year')->nullable(false)->change();
        });
    }
};
