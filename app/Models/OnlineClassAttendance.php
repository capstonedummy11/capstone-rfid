<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineClassAttendance extends Model
{
    protected $primaryKey = 'online_class_attendance_id';

    protected $fillable = [
        'online_class_id',
        'student_id',
        'joined_at',
        'status',
        'is_late',
        'face_required',
        'face_verified',
        'face_verified_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'is_late' => 'boolean',
        'face_required' => 'boolean',
        'face_verified' => 'boolean',
        'face_verified_at' => 'datetime',
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
