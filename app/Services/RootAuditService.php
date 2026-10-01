<?php

namespace App\Services;

use App\Models\RootAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class RootAuditService
{
    public function record(string $action, ?User $actor = null, ?User $target = null, array $metadata = [], ?Request $request = null, ?int $transferId = null, ?int $overrideId = null): RootAuditLog
    {
        return RootAuditLog::query()->create([
            'actor_id' => $actor?->user_id,
            'target_user_id' => $target?->user_id,
            'root_transfer_request_id' => $transferId,
            'root_override_request_id' => $overrideId,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
