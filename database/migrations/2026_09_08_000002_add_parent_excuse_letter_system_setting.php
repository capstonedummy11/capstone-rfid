<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PARENT_EXCUSE_LETTERS_ENABLED_KEY = 'feature.parent_excuse_letters_enabled';

    // @function up: Ginagawa o binabago ang database schema para sa migration na ito.
    // @useIn up: Laravel migration runner
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => self::PARENT_EXCUSE_LETTERS_ENABLED_KEY],
            [
                'value' => json_encode(false),
                'type' => 'boolean',
                'updated_at' => now(),
                'created_at' => now(),
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
            ->where('key', self::PARENT_EXCUSE_LETTERS_ENABLED_KEY)
            ->delete();
    }
};
