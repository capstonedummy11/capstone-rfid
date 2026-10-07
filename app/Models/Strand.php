<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Strand extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'strands';
    protected $primaryKey = 'strand_id';

    protected $fillable = [
        'strand_code',
        'strand_name',
        'department',
        'status',
    ];

    // @function sections: Ibinabalik ang sections Eloquent hasMany relationship.
    // @useIn sections: Eloquent relationship property at eager loading
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'strand_id', 'strand_id');
    }

    // @function students: Ibinabalik ang students Eloquent hasMany relationship.
    // @useIn students: Eloquent relationship property at eager loading
    public function students(): HasMany
    {
        return $this->hasMany(Students::class, 'strand_id', 'strand_id');
    }

    // @function enrollments: Ibinabalik ang enrollments Eloquent hasMany relationship.
    // @useIn enrollments: Eloquent relationship property at eager loading
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'strand_id', 'strand_id');
    }
}
