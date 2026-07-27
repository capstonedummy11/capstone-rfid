<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

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
        'subject_ciphertext',
        'body',
        'body_ciphertext',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
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
