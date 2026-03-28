<?php

namespace App\Models;

use App\Models\AttendanceLog;
use App\Models\Schedule;
use App\Models\Students;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';
    protected $primaryKey = 'attendance_id';

    protected $fillable = [
        'attendance_id',
        'student_id',
        'subject_id',
        'schedule_id',
        'date',
        'time_start',
        'time_end',
        'time_in',
        'time_out',
        'status',
        'subject',
        'room'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function subjectRecord(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'scheduled_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'attendance_id', 'attendance_id');
    }
}
