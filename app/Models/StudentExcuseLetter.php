<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentExcuseLetter extends Model
{
    protected $primaryKey = 'student_excuse_letter_id';

    protected $fillable = [
        'student_id',
        'submitted_by_user_id',
        'submitted_by_role',
        'subject',
        'from_date',
        'to_date',
        'reason',
        'attachment_path',
        'attachment_name',
        'status',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id', 'user_id');
    }
}
