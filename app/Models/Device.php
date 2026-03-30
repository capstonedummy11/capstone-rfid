<?php

namespace App\Models;

use App\Models\Inventory;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
  protected $table = 'items';
  protected $primaryKey = 'item_id';

  protected $fillable = [
    'item_name',
    'item_description',
    'item_sku',
    'item_barcode',
    'item_code',
    'item_type',
    'barcode',
    'brand',
    'model',
    'description',
    'status',
  ];

  public function inventory(): HasOne
  {
    return $this->hasOne(Inventory::class, 'item_id', 'item_id');
  }

  public function transactions(): HasMany
  {
    return $this->hasMany(Transaction::class, 'item_id', 'item_id');
  }

  public function borrowingItems()
  {
    return $this->hasMany(BorrowingItem::class, 'item_id', 'item_id');
  }
}
