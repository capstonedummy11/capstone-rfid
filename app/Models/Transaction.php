<?php

namespace App\Models;

use App\Models\Device;
use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';
    protected $primaryKey = 'transaction_id';

    protected $fillable = [
        'inventory_id',
        'item_id',
        'quantity',
        'transaction_type',
    ];

    // @function inventory: Ibinabalik ang inventory Eloquent belongsTo relationship.
    // @useIn inventory: Eloquent relationship property at eager loading
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id', 'inventory_id');
    }

    // @function item: Ibinabalik ang item Eloquent belongsTo relationship.
    // @useIn item: Eloquent relationship property at eager loading
    public function item(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'item_id', 'item_id');
    }
}