<?php
// FEATURE:excuse-letter-submission - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:excuse-letter-approval - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentExcuseLetter extends Model
{
    protected $primaryKey = 'student_excuse_letter_id';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'student_enrollment_id',
        'submitted_by_user_id',
        'submitted_by_role',
        'subject',
        'from_date',
        'to_date',
        'reason',
        'attachment_path',
        'attachment_name',
        'status',
        'parent_signature',
        'parent_approval_notes',
        'parent_approved_by_user_id',
        'parent_approved_at',
        'recipient_user_ids',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'parent_approved_at' => 'datetime',
        'recipient_user_ids' => 'array',
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

    // @function studentEnrollment: Ibinabalik ang student enrollment Eloquent belongsTo relationship.
    // @useIn studentEnrollment: Eloquent relationship property at eager loading
    public function studentEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id', 'student_enrollment_id');
    }

    // @function submittedBy: Ibinabalik ang submitted by Eloquent belongsTo relationship.
    // @useIn submittedBy: Eloquent relationship property at eager loading
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id', 'user_id');
    }

    // @function parentApprovedBy: Ibinabalik ang parent approved by Eloquent belongsTo relationship.
    // @useIn parentApprovedBy: Eloquent relationship property at eager loading
    public function parentApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_approved_by_user_id', 'user_id');
    }
}
