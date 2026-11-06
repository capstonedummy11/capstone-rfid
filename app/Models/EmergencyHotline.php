<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmergencyHotline extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'emergency_hotline_id';

    protected $fillable = [
        'name',
        'category',
        'phone_number',
        'contact_person',
        'sms_enabled',
        'is_active',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'sms_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];
}
