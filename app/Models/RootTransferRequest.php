<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RootTransferRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime', 'expires_at' => 'datetime', 'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime', 'completed_at' => 'datetime',
            'reminder_7_sent_at' => 'datetime', 'reminder_1_sent_at' => 'datetime',
        ];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id', 'user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id', 'user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }
}
