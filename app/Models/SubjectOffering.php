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

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id', 'instructor_id');
    }

    public function isWritable(): bool
    {
        return $this->academicYear?->isWritable() ?? false;
    }
}
