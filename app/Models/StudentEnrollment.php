<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    use HasFactory;

    protected $primaryKey = 'student_enrollment_id';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'section_id',
        'strand_id',
        'year_level',
        'semester',
        'status',
        'enrolled_at',
        'ended_at',
    ];

    protected $casts = [
        'enrolled_at' => 'date',
        'ended_at' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }

    public function strand(): BelongsTo
    {
        return $this->belongsTo(Strand::class, 'strand_id', 'strand_id');
    }
}
