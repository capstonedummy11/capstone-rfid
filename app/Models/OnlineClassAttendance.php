<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineClassAttendance extends Model
{
    protected $primaryKey = 'online_class_attendance_id';

    protected $fillable = [
        'online_class_id',
        'student_id',
        'academic_year_id',
        'subject_offering_id',
        'student_enrollment_id',
        'joined_at',
        'status',
        'is_late',
        'face_required',
        'face_verified',
        'face_verified_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'is_late' => 'boolean',
        'face_required' => 'boolean',
        'face_verified' => 'boolean',
        'face_verified_at' => 'datetime',
    ];

    public function onlineClass(): BelongsTo
    {
        return $this->belongsTo(OnlineClass::class, 'online_class_id', 'online_class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    public function studentEnrollment(): BelongsTo { return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id', 'student_enrollment_id'); }

    protected static function booted(): void
    {
        static::creating(function (OnlineClassAttendance $attendance) {
            $class = OnlineClass::query()->find($attendance->online_class_id);
            $attendance->academic_year_id ??= $class?->academic_year_id;
            $attendance->subject_offering_id ??= $class?->subject_offering_id;
            if (! $attendance->student_enrollment_id && $class?->academic_year_id) {
                $attendance->student_enrollment_id = StudentEnrollment::query()
                    ->where('student_id', $attendance->student_id)->where('academic_year_id', $class->academic_year_id)
                    ->where('section_id', $class->section_id)->value('student_enrollment_id');
            }
        });
    }
}
