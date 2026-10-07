<?php

use App\Models\SystemSetting;
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
            ['key' => SystemSetting::SECURITY_QUESTIONS],
            [
                'value' => json_encode(SystemSetting::DEFAULT_SECURITY_QUESTIONS),
                'type' => 'array',
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
            ->where('key', SystemSetting::SECURITY_QUESTIONS)
            ->delete();
    }
};
