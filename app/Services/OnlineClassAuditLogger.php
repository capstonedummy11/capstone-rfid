<?php

namespace App\Services;

use App\Models\OnlineClass;
use App\Models\OnlineClassAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class OnlineClassAuditLogger
{
    public function log(
        string $action,
        ?OnlineClass $onlineClass = null,
        ?User $user = null,
        ?Request $request = null,
        ?array $previousValues = null,
        ?array $newValues = null,
    ): void {
        OnlineClassAuditLog::query()->create([
            'online_class_id' => $onlineClass?->online_class_id,
            'user_id' => $user?->user_id,
            'user_role' => $user?->role,
            'action' => $action,
            'section_id' => $onlineClass?->section_id,
            'ip_address' => $request?->ip(),
            'previous_values' => $previousValues,
            'new_values' => $newValues,
            'created_at' => now(),
        ]);
    }
}
