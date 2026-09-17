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
        'academic_year_id',
        'subject_offering_id',
        'student_enrollment_id',
        'time_in',
        'time_out',
        'status',
        'verification_method',
        'time_in_face_path',
        'time_out_face_path',
        'is_late',
        'completion_reason',
        'tap_datetime',
        'tap_type',
        'tap_sequence_number',
        'device_scanner_id',
        'location',
        'validation_result',
        'remarks',
    ];

    protected $casts = [
        'is_late' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (AttendanceLog $log) {
            $attendance = $log->main_attendance_id ? Attendance::query()->find($log->main_attendance_id) : null;
            $log->academic_year_id ??= $attendance?->academic_year_id;
            $log->subject_offering_id ??= $attendance?->subject_offering_id;
            $log->student_enrollment_id ??= $attendance?->student_enrollment_id;
        });
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'main_attendance_id', 'attendance_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id'); }
    public function subjectOffering(): BelongsTo { return $this->belongsTo(SubjectOffering::class, 'subject_offering_id', 'subject_offering_id'); }
    public function studentEnrollment(): BelongsTo { return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id', 'student_enrollment_id'); }
}
