<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $table = 'attendance_logs';

    public $timestamps = false;

    protected $fillable = [
        'attendance_id',
        'main_attendance_id',
        'student_id',
        'schedule_id',
        'time_in',
        'time_out',
        'status',
        'tap_datetime',
        'tap_type',
        'tap_sequence_number',
        'device_scanner_id',
        'location',
        'validation_result',
        'remarks',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'main_attendance_id', 'attendance_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }
}
