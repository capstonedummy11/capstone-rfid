<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineClassAuditLog extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'online_class_audit_log_id';

    protected $fillable = [
        'online_class_id',
        'user_id',
        'user_role',
        'action',
        'section_id',
        'ip_address',
        'previous_values',
        'new_values',
        'created_at',
    ];

    protected $casts = [
        'previous_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function onlineClass(): BelongsTo
    {
        return $this->belongsTo(OnlineClass::class, 'online_class_id', 'online_class_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }
}
