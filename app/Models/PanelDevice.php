<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelDevice extends Model
{
    protected $primaryKey = 'panel_device_id';

    protected $fillable = [
        'label',
        'pin_hash',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
