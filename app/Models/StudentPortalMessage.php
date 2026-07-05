<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class StudentPortalMessage extends Model
{
    protected $primaryKey = 'student_portal_message_id';

    protected $fillable = [
        'student_id',
        'sender_user_id',
        'recipient_user_id',
        'sender_role',
        'instructor_user_id',
        'subject',
        'subject_ciphertext',
        'body',
        'body_ciphertext',
        'attachment_path',
        'attachment_name',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function getSubjectAttribute($value): ?string
    {
        return $this->decryptMessageValue($this->attributes['subject_ciphertext'] ?? null, $value);
    }

    public function setSubjectAttribute($value): void
    {
        $this->attributes['subject_ciphertext'] = $value !== null ? Crypt::encryptString((string) $value) : null;
        $this->attributes['subject'] = $value !== null ? 'Encrypted message' : null;
    }

    public function getBodyAttribute($value): ?string
    {
        return $this->decryptMessageValue($this->attributes['body_ciphertext'] ?? null, $value);
    }

    public function setBodyAttribute($value): void
    {
        $this->attributes['body_ciphertext'] = $value !== null ? Crypt::encryptString((string) $value) : null;
        $this->attributes['body'] = $value !== null ? 'Encrypted message' : null;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id', 'user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id', 'user_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_user_id', 'user_id');
    }

    private function decryptMessageValue(?string $ciphertext, ?string $fallback): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return $fallback;
        }

        try {
            return Crypt::decryptString($ciphertext);
        } catch (DecryptException) {
            return $fallback;
        }
    }
}
