<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
  protected $table = 'inventory';
  protected $primaryKey = 'device_id';

  protected $fillable = [
    'device_name',
    'device_code',
    'device_type',
    'barcode',
    'brand',
    'model',
    'description',
    'status',
  ];

  public function borrowingItems()
  {
    return $this->hasMany(BorrowingItem::class, 'device_id', 'device_id');
  }
}
