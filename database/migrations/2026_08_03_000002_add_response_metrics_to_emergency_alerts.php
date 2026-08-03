<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_alerts', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable()->after('resolved_at');
            $table->timestamp('dispatched_at')->nullable()->after('acknowledged_at');
            $table->unsignedInteger('response_seconds')->nullable()->after('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('emergency_alerts', function (Blueprint $table) {
            $table->dropColumn(['acknowledged_at', 'dispatched_at', 'response_seconds']);
        });
    }
};
