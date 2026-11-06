<?php
// FEATURE:inventory-management - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:console-borrowing - konektadong model, service, route, o UI para sa feature na ito.

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
