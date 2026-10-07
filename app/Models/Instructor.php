<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instructor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'instructors';

    protected $primaryKey = 'instructor_id';

    protected $fillable = [
        'user_id',
        'strand_id',
        'instructor_number',
        'status',
    ];

    // @function user: Ibinabalik ang user Eloquent belongsTo relationship.
    // @useIn user: Eloquent relationship property at eager loading
    /**
     * Get the user associated with the instructor
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // @function strand: Ibinabalik ang strand Eloquent belongsTo relationship.
    // @useIn strand: Eloquent relationship property at eager loading
    /**
     * Get the strand associated with the instructor
     */
    public function strand(): BelongsTo
    {
        return $this->belongsTo(Strand::class, 'strand_id', 'strand_id');
    }

    // @function subjectOfferings: Ibinabalik ang subject offerings Eloquent hasMany relationship.
    // @useIn subjectOfferings: Eloquent relationship property at eager loading
    public function subjectOfferings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class, 'instructor_id', 'instructor_id');
    }
}
