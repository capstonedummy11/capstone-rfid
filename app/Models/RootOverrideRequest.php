<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RootOverrideRequest extends Model
{
    protected $guarded = [];

    // @function casts: Ibinabalik ang field casts ng Root Override Request model.
    // @useIn casts: Eloquent attribute casting lifecycle
    protected function casts(): array
    {
        return ['execute_at' => 'datetime', 'expires_at' => 'datetime', 'rejected_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    // @function requester: Ibinabalik ang requester Eloquent belongsTo relationship.
    // @useIn requester: Eloquent relationship property at eager loading
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id', 'user_id');
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

    // @function approvals: Ibinabalik ang approvals Eloquent hasMany relationship.
    // @useIn approvals: Eloquent relationship property at eager loading
    public function approvals(): HasMany
    {
        return $this->hasMany(RootOverrideApproval::class);
    }
}
