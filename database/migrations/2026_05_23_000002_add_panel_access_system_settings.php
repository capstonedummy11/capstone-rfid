<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'panel.pin_hash'],
            [
                'value' => json_encode(Hash::make((string) config('panel.pin', '1234'))),
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'panel.device_label'],
            [
                'value' => json_encode('Attendance Console'),
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')
            ->whereIn('key', ['panel.pin_hash', 'panel.device_label'])
            ->delete();
    }
};
