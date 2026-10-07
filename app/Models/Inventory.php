<?php

namespace App\Models;

use App\Models\Device;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventories';
    protected $primaryKey = 'inventory_id';

    protected $fillable = [
        'item_id',
        'quantity',
    ];

    // @function item: Ibinabalik ang item Eloquent belongsTo relationship.
    // @useIn item: Eloquent relationship property at eager loading
    public function item(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'item_id', 'item_id');
    }

    // @function transactions: Ibinabalik ang transactions Eloquent hasMany relationship.
    // @useIn transactions: Eloquent relationship property at eager loading
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'inventory_id', 'inventory_id');
    }
}
