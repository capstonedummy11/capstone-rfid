<?php

namespace App\Models;

use App\Models\Attendance;
use App\Models\Instructor;
use App\Models\Laboratory;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Schedule extends Model
{
    use HasFactory;

    protected $table = 'schedules';
    protected $primaryKey = 'scheduled_id';
    public $timestamps = false;

    protected $fillable = [
        'laboratory_id',
        'academic_year_id',
        'subject_offering_id',
        'instructor_id',
        'section_id',
        'subject_code',
        'semester',
        'weekdays',
        'time_start',
        'time_end',
        'room',
    ];

    public function scopeForActiveAcademicYear(Builder $query): Builder
    {
        $activeYearId = AcademicYear::currentOrLatest()?->academic_year_id;

        return $activeYearId
            ? $query->where($query->qualifyColumn('academic_year_id'), $activeYearId)
            : $query;
    }

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class, 'laboratory_id', 'laboratory_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id');
    }

    public function subjectOffering(): BelongsTo
    {
        return $this->belongsTo(SubjectOffering::class, 'subject_offering_id', 'subject_offering_id');
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

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'schedule_id', 'scheduled_id');
    }

    public function onlineClasses(): HasMany
    {
        return $this->hasMany(OnlineClass::class, 'schedule_id', 'scheduled_id');
    }
}
