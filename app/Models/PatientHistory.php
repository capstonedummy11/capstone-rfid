<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientHistory extends Model
{
    use HasFactory;

    protected $primaryKey = 'patient_history_id';

    protected $fillable = [
        'student_id',
        'user_id',
        'recorded_by_user_id',
        'patient_type',
        'patient_name',
        'summary',
        'notes',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];
}
