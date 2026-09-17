<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('attendances') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE attendances MODIFY time_in TIME NULL");
            DB::statement("ALTER TABLE attendances MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        } elseif (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->time('time_in')->nullable()->change();
                $table->string('status', 50)->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        // Manual or excused records may not have a tap time, so this is intentionally irreversible.
    }
};
