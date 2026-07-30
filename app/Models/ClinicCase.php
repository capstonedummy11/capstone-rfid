<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicCase extends Model
{
    use HasFactory;

    protected $primaryKey = 'clinic_case_id';

    protected $fillable = [
        'emergency_alert_id',
        'student_id',
        'user_id',
        'handled_by_user_id',
        'patient_type',
        'patient_name',
        'case_type',
        'symptoms',
        'action_taken',
        'notes',
        'status',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(EmergencyAlert::class, 'emergency_alert_id', 'emergency_alert_id');
    }

    public function assignedResponder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id', 'user_id');
    }
}
