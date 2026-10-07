<?php

namespace App\Models;

use App\Models\Schedule;
use App\Models\Strand;
use App\Models\Students;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sections';
    protected $primaryKey = 'section_id';

    protected $fillable = [
        'section_id',
        'strand_id',
        'academic_year_id',
        'section_name',
        'year_level',
        'semester',
        'school_year',
        'status'
    ];

    // @function strand: Ibinabalik ang strand Eloquent belongsTo relationship.
    // @useIn strand: Eloquent relationship property at eager loading
    public function strand(): BelongsTo
    {
        return $this->belongsTo(Strand::class, 'strand_id', 'strand_id');
    }

    // @function academicYear: Ibinabalik ang academic year Eloquent belongsTo relationship.
    // @useIn academicYear: Eloquent relationship property at eager loading
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id');
    }

    // @function students: Ibinabalik ang students Eloquent hasMany relationship.
    // @useIn students: Eloquent relationship property at eager loading
    public function students(): HasMany
    {
        return $this->hasMany(Students::class, 'section_id', 'section_id');
    }

    // @function subjects: Ibinabalik ang subjects Eloquent hasMany relationship.
    // @useIn subjects: Eloquent relationship property at eager loading
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'section_id', 'section_id');
    }

    // @function schedules: Ibinabalik ang schedules Eloquent hasMany relationship.
    // @useIn schedules: Eloquent relationship property at eager loading
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'section_id', 'section_id');
    }

    // @function enrollments: Ibinabalik ang enrollments Eloquent hasMany relationship.
    // @useIn enrollments: Eloquent relationship property at eager loading
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'section_id', 'section_id');
    }

    // @function subjectOfferings: Ibinabalik ang subject offerings Eloquent hasMany relationship.
    // @useIn subjectOfferings: Eloquent relationship property at eager loading
    public function subjectOfferings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class, 'section_id', 'section_id');
    }
}
