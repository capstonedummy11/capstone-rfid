<?php
// FEATURE:rfid-attendance - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:attendance-review - konektadong model, service, route, o UI para sa feature na ito.

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

    // @function booted: Nirerehistro ang model event hooks para sa Attendance.
    // @useIn booted: Eloquent model boot lifecycle
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

    // @function subjectRecord: Ibinabalik ang subject record Eloquent belongsTo relationship.
    // @useIn subjectRecord: Eloquent relationship property at eager loading
    public function subjectRecord(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }

    // @function schedule: Ibinabalik ang schedule Eloquent belongsTo relationship.
    // @useIn schedule: Eloquent relationship property at eager loading
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'scheduled_id');
    }

    // @function student: Ibinabalik ang student Eloquent belongsTo relationship.
    // @useIn student: Eloquent relationship property at eager loading
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    // @function academicYear: Ibinabalik ang academic year Eloquent belongsTo relationship.
    // @useIn academicYear: Eloquent relationship property at eager loading
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id'); }
    // @function subjectOffering: Ibinabalik ang subject offering Eloquent belongsTo relationship.
    // @useIn subjectOffering: Eloquent relationship property at eager loading
    public function subjectOffering(): BelongsTo { return $this->belongsTo(SubjectOffering::class, 'subject_offering_id', 'subject_offering_id'); }
    // @function studentEnrollment: Ibinabalik ang student enrollment Eloquent belongsTo relationship.
    // @useIn studentEnrollment: Eloquent relationship property at eager loading
    public function studentEnrollment(): BelongsTo { return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id', 'student_enrollment_id'); }

    // @function attendanceLogs: Ibinabalik ang attendance logs Eloquent hasMany relationship.
    // @useIn attendanceLogs: Eloquent relationship property at eager loading
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'main_attendance_id', 'attendance_id');
    }
}
