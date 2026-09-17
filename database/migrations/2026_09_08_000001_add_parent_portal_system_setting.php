<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PARENT_PORTAL_ENABLED_KEY = 'feature.parent_portal_enabled';

    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => self::PARENT_PORTAL_ENABLED_KEY],
            [
                'value' => json_encode(false),
                'type' => 'boolean',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')
            ->where('key', self::PARENT_PORTAL_ENABLED_KEY)
            ->delete();
    }
};
