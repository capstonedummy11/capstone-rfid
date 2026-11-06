<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // @function up: Ginagawa o binabago ang database schema para sa migration na ito.
    // @useIn up: Laravel migration runner
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'attendance.absent_default_days'],
            [
                'value' => json_encode(15),
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    // @function down: Ibinabalik ang schema changes ng migration na ito.
    // @useIn down: Laravel migration runner
    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')
            ->where('key', 'attendance.absent_default_days')
            ->delete();
    }
};
