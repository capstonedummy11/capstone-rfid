<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RootAuditLogResource extends JsonResource
{
    // @function toArray: Kinukuha ang to array result para sa Root Audit Log Resource.
    // @useIn toArray: Laravel JSON resource serialization
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'actor' => $this->actor?->email, 'action' => $this->action, 'target' => $this->target?->email, 'ip_address' => $this->ip_address, 'metadata' => $this->metadata, 'created_at' => $this->created_at?->toIso8601String()];
    }
}
