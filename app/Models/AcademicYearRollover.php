<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYearRollover extends Model
{
    protected $primaryKey = 'academic_year_rollover_id';
    protected $fillable = ['source_academic_year_id', 'destination_academic_year_id', 'executed_by_user_id', 'mode', 'status', 'preview_counts', 'execution_counts', 'errors', 'started_at', 'completed_at'];
    protected $casts = ['preview_counts' => 'array', 'execution_counts' => 'array', 'errors' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    // @function sourceYear: Ibinabalik ang source year Eloquent belongsTo relationship.
    // @useIn sourceYear: Eloquent relationship property at eager loading
    public function sourceYear(): BelongsTo { return $this->belongsTo(AcademicYear::class, 'source_academic_year_id', 'academic_year_id'); }
    // @function destinationYear: Ibinabalik ang destination year Eloquent belongsTo relationship.
    // @useIn destinationYear: Eloquent relationship property at eager loading
    public function destinationYear(): BelongsTo { return $this->belongsTo(AcademicYear::class, 'destination_academic_year_id', 'academic_year_id'); }
    // @function items: Ibinabalik ang items Eloquent hasMany relationship.
    // @useIn items: Eloquent relationship property at eager loading
    public function items(): HasMany { return $this->hasMany(AcademicYearRolloverItem::class, 'academic_year_rollover_id', 'academic_year_rollover_id'); }
}
