<?php
// FEATURE:online-class-management - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:online-class-join - konektadong model, service, route, o UI para sa feature na ito.

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

    // @function schedule: Ibinabalik ang schedule Eloquent belongsTo relationship.
    // @useIn schedule: Eloquent relationship property at eager loading
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'scheduled_id');
    }

    // @function academicYear: Ibinabalik ang academic year Eloquent belongsTo relationship.
    // @useIn academicYear: Eloquent relationship property at eager loading
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id'); }
    // @function subjectOffering: Ibinabalik ang subject offering Eloquent belongsTo relationship.
    // @useIn subjectOffering: Eloquent relationship property at eager loading
    public function subjectOffering(): BelongsTo { return $this->belongsTo(SubjectOffering::class, 'subject_offering_id', 'subject_offering_id'); }

    // @function booted: Nirerehistro ang model event hooks para sa Online Class.
    // @useIn booted: Eloquent model boot lifecycle
    protected static function booted(): void
    {
        static::saving(function (OnlineClass $class) {
            $schedule = $class->schedule_id ? Schedule::query()->find($class->schedule_id) : null;
            $class->academic_year_id = $schedule?->academic_year_id;
            $class->subject_offering_id = $schedule?->subject_offering_id;
        });
    }

    // @function instructor: Ibinabalik ang instructor Eloquent belongsTo relationship.
    // @useIn instructor: Eloquent relationship property at eager loading
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id', 'instructor_id');
    }

    // @function section: Ibinabalik ang section Eloquent belongsTo relationship.
    // @useIn section: Eloquent relationship property at eager loading
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }

    // @function subject: Ibinabalik ang subject Eloquent belongsTo relationship.
    // @useIn subject: Eloquent relationship property at eager loading
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_code', 'subject_code');
    }

    // @function attachments: Ibinabalik ang attachments Eloquent hasMany relationship.
    // @useIn attachments: Eloquent relationship property at eager loading
    public function attachments(): HasMany
    {
        return $this->hasMany(OnlineClassAttachment::class, 'online_class_id', 'online_class_id');
    }

    // @function attendances: Ibinabalik ang attendances Eloquent hasMany relationship.
    // @useIn attendances: Eloquent relationship property at eager loading
    public function attendances(): HasMany
    {
        return $this->hasMany(OnlineClassAttendance::class, 'online_class_id', 'online_class_id');
    }
}
