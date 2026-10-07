<?php
// FEATURE:messenger - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:excuse-letter-review - konektadong model, service, route, o UI para sa feature na ito.

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
        'student_excuse_letter_id',
        'excuse_letter_review_decision',
        'excuse_letter_reviewed_by_user_id',
        'excuse_letter_reviewed_at',
        'excuse_letter_review_email_subject',
        'excuse_letter_review_email_body',
        'excuse_letter_review_recipients',
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
        'attachment_mime',
        'attachment_size',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'excuse_letter_reviewed_at' => 'datetime',
        'excuse_letter_review_recipients' => 'array',
    ];

    // @function getSubjectAttribute: Kinukuha ang subject attribute sa Student Portal Message flow.
    // @useIn getSubjectAttribute: Eloquent attribute read/write lifecycle
    public function getSubjectAttribute($value): ?string
    {
        return $this->decryptMessageValue($this->attributes['subject_ciphertext'] ?? null, $value);
    }

    // @function setSubjectAttribute: Sine-set ang subject attribute sa Student Portal Message flow.
    // @useIn setSubjectAttribute: Eloquent attribute read/write lifecycle
    public function setSubjectAttribute($value): void
    {
        $this->attributes['subject_ciphertext'] = $value !== null ? Crypt::encryptString((string) $value) : null;
        $this->attributes['subject'] = $value !== null ? 'Encrypted message' : null;
    }

    // @function getBodyAttribute: Kinukuha ang body attribute sa Student Portal Message flow.
    // @useIn getBodyAttribute: Eloquent attribute read/write lifecycle
    public function getBodyAttribute($value): ?string
    {
        return $this->decryptMessageValue($this->attributes['body_ciphertext'] ?? null, $value);
    }

    // @function setBodyAttribute: Sine-set ang body attribute sa Student Portal Message flow.
    // @useIn setBodyAttribute: Eloquent attribute read/write lifecycle
    public function setBodyAttribute($value): void
    {
        $this->attributes['body_ciphertext'] = $value !== null ? Crypt::encryptString((string) $value) : null;
        $this->attributes['body'] = $value !== null ? 'Encrypted message' : null;
    }

    // @function student: Ibinabalik ang student Eloquent belongsTo relationship.
    // @useIn student: Eloquent relationship property at eager loading
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    // @function excuseLetter: Ibinabalik ang excuse letter Eloquent belongsTo relationship.
    // @useIn excuseLetter: Eloquent relationship property at eager loading
    public function excuseLetter(): BelongsTo
    {
        return $this->belongsTo(StudentExcuseLetter::class, 'student_excuse_letter_id', 'student_excuse_letter_id');
    }

    // @function excuseLetterReviewedBy: Ibinabalik ang excuse letter reviewed by Eloquent belongsTo relationship.
    // @useIn excuseLetterReviewedBy: Eloquent relationship property at eager loading
    public function excuseLetterReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excuse_letter_reviewed_by_user_id', 'user_id');
    }

    // @function sender: Ibinabalik ang sender Eloquent belongsTo relationship.
    // @useIn sender: Eloquent relationship property at eager loading
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id', 'user_id');
    }

    // @function recipient: Ibinabalik ang recipient Eloquent belongsTo relationship.
    // @useIn recipient: Eloquent relationship property at eager loading
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id', 'user_id');
    }

    // @function instructor: Ibinabalik ang instructor Eloquent belongsTo relationship.
    // @useIn instructor: Eloquent relationship property at eager loading
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_user_id', 'user_id');
    }

    // @function decryptMessageValue: Binubuo ang decrypt message value string para sa Student Portal Message.
    // @useIn decryptMessageValue: StudentPortalMessage::getSubjectAttribute (app/Models/StudentPortalMessage.php)
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
