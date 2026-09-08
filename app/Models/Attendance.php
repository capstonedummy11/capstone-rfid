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
        'schedule_id',
        'academic_year_id',
        'subject_offering_id',
        'student_enrollment_id',
        'date',
        'time_start',
        'time_end',
        'time_in',
        'time_out',
        'check_in_status',
        'status',
        'room_status',
        'total_taps',
        'remarks',
        'subject_code',
        'room'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Attendance $attendance) {
            if (! $attendance->schedule_id) {
                return;
            }
            $schedule = Schedule::query()->find($attendance->schedule_id);
            $attendance->academic_year_id ??= $schedule?->academic_year_id;
            $attendance->subject_offering_id ??= $schedule?->subject_offering_id;
            if (! $attendance->student_enrollment_id && $attendance->student_id && $schedule?->academic_year_id) {
                $query = StudentEnrollment::query()->where('student_id', $attendance->student_id)->where('academic_year_id', $schedule->academic_year_id);
                $enrollment = $schedule->semester ? (clone $query)->where('semester', $schedule->semester)->first() : null;
                $attendance->student_enrollment_id = ($enrollment ?? $query->first())?->student_enrollment_id;
            }
        });
    }

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

    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id'); }
    public function subjectOffering(): BelongsTo { return $this->belongsTo(SubjectOffering::class, 'subject_offering_id', 'subject_offering_id'); }
    public function studentEnrollment(): BelongsTo { return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id', 'student_enrollment_id'); }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'main_attendance_id', 'attendance_id');
    }
}
