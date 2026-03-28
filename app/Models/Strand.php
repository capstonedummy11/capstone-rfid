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

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'strand_id', 'strand_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Students::class, 'strand_id', 'strand_id');
    }
}