<?php
// FEATURE:emergency-alerts - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:clinic-dispatch - konektadong model, service, route, o UI para sa feature na ito.

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
        'sub_type',
        'severity',
        'status',
        'message',
        'metadata',
        'resolved_at',
        'acknowledged_at',
        'dispatched_at',
        'response_seconds',
    ];

    protected $casts = [
        'metadata' => 'array',
        'resolved_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'response_seconds' => 'integer',
    ];

    // @function type: Ibinabalik ang type Eloquent belongsTo relationship.
    // @useIn type: Eloquent relationship property at eager loading
    public function type(): BelongsTo
    {
        return $this->belongsTo(EmergencyType::class, 'emergency_type_id', 'emergency_type_id');
    }

    // @function cases: Ibinabalik ang cases Eloquent hasMany relationship.
    // @useIn cases: Eloquent relationship property at eager loading
    public function cases(): HasMany
    {
        return $this->hasMany(ClinicCase::class, 'emergency_alert_id', 'emergency_alert_id');
    }
}
