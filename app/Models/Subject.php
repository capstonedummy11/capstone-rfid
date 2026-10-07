<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory, SoftDeletes;

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

    // @function section: Ibinabalik ang section Eloquent belongsTo relationship.
    // @useIn section: Eloquent relationship property at eager loading
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }

    // @function user: Ibinabalik ang user Eloquent belongsTo relationship.
    // @useIn user: Eloquent relationship property at eager loading
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // @function schedules: Ibinabalik ang schedules Eloquent hasMany relationship.
    // @useIn schedules: Eloquent relationship property at eager loading
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'subject_code', 'subject_code');
    }

    // @function offerings: Ibinabalik ang offerings Eloquent hasMany relationship.
    // @useIn offerings: Eloquent relationship property at eager loading
    public function offerings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class, 'subject_id', 'subject_id');
    }

    // @function attendances: Ibinabalik ang attendances Eloquent hasMany relationship.
    // @useIn attendances: Eloquent relationship property at eager loading
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'subject_id', 'subject_id');
    }
}
