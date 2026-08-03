<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_ARCHIVED = 'archived';

    protected $primaryKey = 'academic_year_id';

    protected $fillable = [
        'name',
        'starts_on',
        'ends_on',
        'status',
        'active_semester',
        'activated_at',
        'activated_by_user_id',
        'closed_at',
        'closed_by_user_id',
        'reopened_at',
        'reopened_by_user_id',
        'reopen_reason',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'activated_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public static function active(): ?self
    {
        return static::query()->where('status', self::STATUS_ACTIVE)->first();
    }

    public function isWritable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_ACTIVE], true);
    }

    public function subjectOfferings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class, 'academic_year_id', 'academic_year_id');
    }
}
