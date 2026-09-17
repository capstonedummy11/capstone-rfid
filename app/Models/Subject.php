<?php

namespace App\Models;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $table = 'subjects';
    protected $primaryKey = 'subject_id';

    protected $fillable = [
        'section_id',
        'user_id',
        'subject_name',
        'subject_code',
        'subject_description',
        'department',
        'unit',
        'semester',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'subject_code', 'subject_code');
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class, 'subject_id', 'subject_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'subject_id', 'subject_id');
    }
}
