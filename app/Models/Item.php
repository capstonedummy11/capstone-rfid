<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'inventory_items';
    protected $fillable = [
        'item_id',
        'barcode',
        'name',
        'description',
        'sku',
        'status'
    ];
}
