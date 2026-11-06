<?php
// FEATURE:academic-scheduling - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:student-management - konektadong model, service, route, o UI para sa feature na ito.

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

    // @function student: Ibinabalik ang student Eloquent belongsTo relationship.
    // @useIn student: Eloquent relationship property at eager loading
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    // @function academicYear: Ibinabalik ang academic year Eloquent belongsTo relationship.
    // @useIn academicYear: Eloquent relationship property at eager loading
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id');
    }

    // @function section: Ibinabalik ang section Eloquent belongsTo relationship.
    // @useIn section: Eloquent relationship property at eager loading
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }

    // @function strand: Ibinabalik ang strand Eloquent belongsTo relationship.
    // @useIn strand: Eloquent relationship property at eager loading
    public function strand(): BelongsTo
    {
        return $this->belongsTo(Strand::class, 'strand_id', 'strand_id');
    }
}
