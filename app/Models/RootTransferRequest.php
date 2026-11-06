<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RootTransferRequest extends Model
{
    protected $guarded = [];

    // @function casts: Ibinabalik ang field casts ng Root Transfer Request model.
    // @useIn casts: Eloquent attribute casting lifecycle
    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime', 'expires_at' => 'datetime', 'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime', 'completed_at' => 'datetime',
            'reminder_7_sent_at' => 'datetime', 'reminder_1_sent_at' => 'datetime',
        ];
    }

    // @function fromUser: Ibinabalik ang from user Eloquent belongsTo relationship.
    // @useIn fromUser: Eloquent relationship property at eager loading
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id', 'user_id');
    }

    // @function toUser: Ibinabalik ang to user Eloquent belongsTo relationship.
    // @useIn toUser: Eloquent relationship property at eager loading
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id', 'user_id');
    }

    // @function requester: Ibinabalik ang requester Eloquent belongsTo relationship.
    // @useIn requester: Eloquent relationship property at eager loading
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }
}
