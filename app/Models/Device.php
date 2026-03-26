<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
  protected $table = 'items';
  protected $primaryKey = 'item_id';

  protected $fillable = [
    'item_name',
    'item_code',
    'item_type',
    'barcode',
    'brand',
    'model',
    'description',
    'status',
  ];

  public function borrowingItems()
  {
    return $this->hasMany(BorrowingItem::class, 'item_id', 'item_id');
  }
}
