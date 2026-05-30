<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('emergency_alerts') && ! Schema::hasColumn('emergency_alerts', 'sub_type')) {
            Schema::table('emergency_alerts', function (Blueprint $table) {
                $table->string('sub_type')->nullable()->after('triggered_by_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('emergency_alerts') && Schema::hasColumn('emergency_alerts', 'sub_type')) {
            Schema::table('emergency_alerts', function (Blueprint $table) {
                $table->dropColumn('sub_type');
            });
        }
    }
};
