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
        'student_id',
        'time_in',
        'time_out',
        'status',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id', 'attendance_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }
}