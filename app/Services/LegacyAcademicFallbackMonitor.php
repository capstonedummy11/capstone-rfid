<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyAcademicFallbackMonitor
{
    public static function record(string $context, array $payload = []): void
    {
        if (! Schema::hasTable('legacy_academic_fallback_events')) return;
        $existing = DB::table('legacy_academic_fallback_events')->where('context', $context)->first();
        if ($existing) {
            DB::table('legacy_academic_fallback_events')->where('legacy_academic_fallback_event_id', $existing->legacy_academic_fallback_event_id)->update([
                'use_count' => $existing->use_count + 1, 'last_used_at' => now(), 'last_payload' => json_encode($payload), 'updated_at' => now(),
            ]);
            return;
        }
        DB::table('legacy_academic_fallback_events')->insert([
            'context' => $context, 'use_count' => 1, 'last_used_at' => now(), 'last_payload' => json_encode($payload), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
