<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcuseLetter extends Model
{
    protected $fillable = [
        'user_id',
        'student_id',
        'submitted_by_role',
        'subject',
        'from_date',
        'to_date',
        'reason',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'status',
        'review_notes',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    // @function user: Ibinabalik ang user Eloquent belongsTo relationship.
    // @useIn user: Eloquent relationship property at eager loading
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // @function student: Ibinabalik ang student Eloquent belongsTo relationship.
    // @useIn student: Eloquent relationship property at eager loading
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    // @function reviewer: Ibinabalik ang reviewer Eloquent belongsTo relationship.
    // @useIn reviewer: Eloquent relationship property at eager loading
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'user_id');
    }
}
