<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RootOverrideRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['execute_at' => 'datetime', 'expires_at' => 'datetime', 'rejected_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id', 'user_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id', 'user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id', 'user_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RootOverrideApproval::class);
    }
}
