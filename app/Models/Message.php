<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $primaryKey = 'message_id';

    protected $fillable = [
        'instructor_user_id',
        'sender_type',
        'sender_name',
        'sender_email',
        'student_number',
        'subject',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_user_id', 'user_id');
    }
}
