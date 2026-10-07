<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RootAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    // @function casts: Ibinabalik ang field casts ng Root Audit Log model.
    // @useIn casts: Eloquent attribute casting lifecycle
    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    // @function actor: Ibinabalik ang actor Eloquent belongsTo relationship.
    // @useIn actor: Eloquent relationship property at eager loading
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id', 'user_id');
    }

    // @function target: Ibinabalik ang target Eloquent belongsTo relationship.
    // @useIn target: Eloquent relationship property at eager loading
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id', 'user_id');
    }

    // @function booted: Nirerehistro ang model event hooks para sa Root Audit Log.
    // @useIn booted: Eloquent model boot lifecycle
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Root ownership audit logs are immutable.'));
        static::deleting(fn () => throw new \LogicException('Root ownership audit logs are immutable.'));
    }
}
