<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BorrowingItem extends Model
{
  protected $table = 'borrowing_items';

  protected $fillable = [
    'borrowing_id',
    'item_id',
    'quantity',
    'status',
  ];

  public function borrowing()
  {
    return $this->belongsTo(Borrowing::class, 'borrowing_id', 'borrowing_id');
  }

  public function item()
  {
    return $this->belongsTo(Device::class, 'item_id', 'item_id');
  }

  public function device()
  {
    return $this->item();
  }
}
