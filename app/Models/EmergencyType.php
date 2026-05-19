<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmergencyType extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'emergency_type_id';

    protected $fillable = [
        'name',
        'category',
        'default_message',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function alerts(): HasMany
    {
        return $this->hasMany(EmergencyAlert::class, 'emergency_type_id', 'emergency_type_id');
    }
}
