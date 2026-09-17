<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineClassNotification extends Model
{
    protected $primaryKey = 'online_class_notification_id';

    protected $fillable = [
        'online_class_id',
        'student_id',
        'academic_year_id',
        'subject_offering_id',
        'student_enrollment_id',
        'event',
        'title',
        'body',
        'read_at',
        'email_sent_at',
        'email_error',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'email_sent_at' => 'datetime',
    ];

    public function onlineClass(): BelongsTo
    {
        return $this->belongsTo(OnlineClass::class, 'online_class_id', 'online_class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }
}
