<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RootTransferResource extends JsonResource
{
    // @function toArray: Kinukuha ang to array result para sa Root Transfer Resource.
    // @useIn toArray: Laravel JSON resource serialization
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'status' => $this->status,
            'from' => ['id' => $this->fromUser->user_id, 'name' => $this->fromUser->name, 'email' => $this->fromUser->email],
            'to' => ['id' => $this->toUser->user_id, 'name' => $this->toUser->name, 'email' => $this->toUser->email],
            'requested_by' => $this->requester->email, 'effective_at' => $this->effective_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(), 'accepted_at' => $this->accepted_at?->toIso8601String(),
        ];
    }
}
