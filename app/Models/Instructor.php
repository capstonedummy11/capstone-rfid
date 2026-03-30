<?php

namespace App\Models;

use App\Models\Strand;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Instructor extends Model
{
    use HasFactory;

    protected $table = 'instructors';
    protected $primaryKey = 'instructor_id';

    protected $fillable = [
        'user_id',
        'strand_id',
        'instructor_number',
        'status'
    ];

    /**
     * Get the user associated with the instructor
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the strand associated with the instructor
     */
    public function strand(): BelongsTo
    {
        return $this->belongsTo(Strand::class, 'strand_id', 'strand_id');
    }
}
