<?php
// FEATURE:online-class-notifications - konektadong model, service, route, o UI para sa feature na ito.

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

    // @function onlineClass: Ibinabalik ang online class Eloquent belongsTo relationship.
    // @useIn onlineClass: Eloquent relationship property at eager loading
    public function onlineClass(): BelongsTo
    {
        return $this->belongsTo(OnlineClass::class, 'online_class_id', 'online_class_id');
    }

    // @function student: Ibinabalik ang student Eloquent belongsTo relationship.
    // @useIn student: Eloquent relationship property at eager loading
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }
}
