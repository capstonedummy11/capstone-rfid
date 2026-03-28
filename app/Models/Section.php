<?php

namespace App\Models;

use App\Models\Schedule;
use App\Models\Strand;
use App\Models\Students;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $table = 'sections';
    protected $primaryKey = 'section_id';

    protected $fillable = [
        'section_id',
        'strand_id',
        'section_name',
        'year_level',
        'semester',
        'school_year',
        'status'
    ];

    public function strand(): BelongsTo
    {
        return $this->belongsTo(Strand::class, 'strand_id', 'strand_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Students::class, 'section_id', 'section_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'section_id', 'section_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'section_id', 'section_id');
    }
}
