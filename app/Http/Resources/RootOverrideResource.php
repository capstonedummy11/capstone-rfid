<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RootOverrideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'status' => $this->status, 'reason' => $this->reason,
            'requester_id' => $this->requester_id, 'requester' => $this->requester->email,
            'to' => ['id' => $this->toUser->user_id, 'name' => $this->toUser->name, 'email' => $this->toUser->email],
            'required_approvals' => $this->required_approvals,
            'approvals_count' => $this->approvals->where('decision', 'approve')->count(),
            'approvals' => $this->approvals->map(fn ($approval) => ['approver_id' => $approval->approver_id, 'approver' => $approval->approver->email, 'decision' => $approval->decision, 'created_at' => $approval->created_at?->toIso8601String()])->values(),
            'execute_at' => $this->execute_at?->toIso8601String(), 'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
