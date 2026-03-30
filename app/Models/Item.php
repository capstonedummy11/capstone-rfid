<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Item extends Model
{
    use SoftDeletes;
    protected $table = 'inventory_items';
    protected $primaryKey = 'item_id';

    protected $fillable = [
        'item_id',
        'barcode',
        'name',
        'description',
        'sku',
        'status'
    ];
}
