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

    // @function getSubjectAttribute: Kinukuha ang subject attribute sa Message flow.
    // @useIn getSubjectAttribute: Eloquent attribute read/write lifecycle
    public function getSubjectAttribute($value): ?string
    {
        return $this->decryptMessageValue($this->attributes['subject_ciphertext'] ?? null, $value);
    }

    // @function setSubjectAttribute: Sine-set ang subject attribute sa Message flow.
    // @useIn setSubjectAttribute: Eloquent attribute read/write lifecycle
    public function setSubjectAttribute($value): void
    {
        $this->attributes['subject_ciphertext'] = $value !== null ? Crypt::encryptString((string) $value) : null;
        $this->attributes['subject'] = $value !== null ? 'Encrypted message' : null;
    }

    // @function getBodyAttribute: Kinukuha ang body attribute sa Message flow.
    // @useIn getBodyAttribute: Eloquent attribute read/write lifecycle
    public function getBodyAttribute($value): ?string
    {
        return $this->decryptMessageValue($this->attributes['body_ciphertext'] ?? null, $value);
    }

    // @function setBodyAttribute: Sine-set ang body attribute sa Message flow.
    // @useIn setBodyAttribute: Eloquent attribute read/write lifecycle
    public function setBodyAttribute($value): void
    {
        $this->attributes['body_ciphertext'] = $value !== null ? Crypt::encryptString((string) $value) : null;
        $this->attributes['body'] = $value !== null ? 'Encrypted message' : null;
    }

    // @function instructor: Ibinabalik ang instructor Eloquent belongsTo relationship.
    // @useIn instructor: Eloquent relationship property at eager loading
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_user_id', 'user_id');
    }

    // @function decryptMessageValue: Binubuo ang decrypt message value string para sa Message.
    // @useIn decryptMessageValue: Message::getSubjectAttribute (app/Models/Message.php)
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
