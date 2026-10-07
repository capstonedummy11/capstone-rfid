<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectOffering extends Model
{
    use HasFactory;

    protected $primaryKey = 'subject_offering_id';

    protected $fillable = [
        'academic_year_id',
        'subject_id',
        'section_id',
        'instructor_id',
        'semester',
        'status',
    ];

    // @function academicYear: Ibinabalik ang academic year Eloquent belongsTo relationship.
    // @useIn academicYear: Eloquent relationship property at eager loading
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id');
    }

    // @function subject: Ibinabalik ang subject Eloquent belongsTo relationship.
    // @useIn subject: Eloquent relationship property at eager loading
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }

    // @function section: Ibinabalik ang section Eloquent belongsTo relationship.
    // @useIn section: Eloquent relationship property at eager loading
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }

    // @function instructor: Ibinabalik ang instructor Eloquent belongsTo relationship.
    // @useIn instructor: Eloquent relationship property at eager loading
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id', 'instructor_id')->withTrashed();
    }

    // @function isWritable: Sinusuri kung writable para sa Subject Offering.
    // @useIn isWritable: app/Http/Controllers/SubjectController.php
    public function isWritable(): bool
    {
        return $this->academicYear?->isWritable() ?? false;
    }
}
