<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Laboratory extends Model
{
    use HasFactory;

    protected $table = 'laboratories';
    protected $primaryKey = 'laboratory_id';

    protected $fillable = [
        'name',
        'description',
        'location',
        'status'
    ];

    public function panelDevices(): HasMany
    {
        return $this->hasMany(PanelDevice::class, 'laboratory_id', 'laboratory_id');
    }
}
