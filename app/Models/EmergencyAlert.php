<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmergencyAlert extends Model
{
    use HasFactory;

    protected $primaryKey = 'emergency_alert_id';

    protected $fillable = [
        'emergency_type_id',
        'triggered_by_user_id',
        'schedule_id',
        'room',
        'subject_code',
        'triggered_by_name',
        'severity',
        'status',
        'message',
        'metadata',
        'resolved_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(EmergencyType::class, 'emergency_type_id', 'emergency_type_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(ClinicCase::class, 'emergency_alert_id', 'emergency_alert_id');
    }
}
