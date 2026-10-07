<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanelDevice extends Model
{
    protected $primaryKey = 'panel_device_id';

    protected $fillable = [
        'laboratory_id',
        'label',
        'description',
        'pin_hash',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // @function laboratory: Ibinabalik ang laboratory Eloquent belongsTo relationship.
    // @useIn laboratory: Eloquent relationship property at eager loading
    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class, 'laboratory_id', 'laboratory_id');
    }
}
