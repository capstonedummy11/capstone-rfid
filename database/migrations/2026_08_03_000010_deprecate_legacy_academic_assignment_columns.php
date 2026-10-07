<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // @function up: Ginagawa o binabago ang database schema para sa migration na ito.
    // @useIn up: Laravel migration runner
    public function up(): void
    {
        Schema::create('legacy_academic_fallback_events', function (Blueprint $table) {
            $table->id('legacy_academic_fallback_event_id');
            $table->string('context')->unique();
            $table->unsignedBigInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->json('last_payload')->nullable();
            $table->timestamps();
        });
    }

    // @function down: Ibinabalik ang schema changes ng migration na ito.
    // @useIn down: Laravel migration runner
    public function down(): void
    {
        Schema::dropIfExists('legacy_academic_fallback_events');
    }
};
