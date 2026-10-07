<?php
// FEATURE:clinic-records - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:clinic-dispatch - konektadong model, service, route, o UI para sa feature na ito.

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

    // @function alert: Ibinabalik ang alert Eloquent belongsTo relationship.
    // @useIn alert: Eloquent relationship property at eager loading
    public function alert(): BelongsTo
    {
        return $this->belongsTo(EmergencyAlert::class, 'emergency_alert_id', 'emergency_alert_id');
    }

    // @function assignedResponder: Ibinabalik ang assigned responder Eloquent belongsTo relationship.
    // @useIn assignedResponder: Eloquent relationship property at eager loading
    public function assignedResponder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id', 'user_id');
    }
}
