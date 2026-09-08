<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnlineClass extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'online_class_id';

    protected $fillable = [
        'schedule_id',
        'academic_year_id',
        'subject_offering_id',
        'instructor_id',
        'section_id',
        'subject_code',
        'title',
        'description',
        'meeting_link',
        'scheduled_date',
        'start_time',
        'end_time',
        'require_face_recognition',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'require_face_recognition' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'scheduled_id');
    }

    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id'); }
    public function subjectOffering(): BelongsTo { return $this->belongsTo(SubjectOffering::class, 'subject_offering_id', 'subject_offering_id'); }

    protected static function booted(): void
    {
        static::saving(function (OnlineClass $class) {
            $schedule = $class->schedule_id ? Schedule::query()->find($class->schedule_id) : null;
            $class->academic_year_id = $schedule?->academic_year_id;
            $class->subject_offering_id = $schedule?->subject_offering_id;
        });
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id', 'instructor_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_code', 'subject_code');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(OnlineClassAttachment::class, 'online_class_id', 'online_class_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(OnlineClassAttendance::class, 'online_class_id', 'online_class_id');
    }
}
