<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowingItem extends Model
{
  protected $table = 'borrowing_items';

  protected $fillable = [
    'borrowing_id',
    'item_id',
    'quantity',
    'status',
  ];

  public function borrowing(): BelongsTo
  {
    return $this->belongsTo(Borrowing::class, 'borrowing_id', 'borrowing_id');
  }

  public function item(): BelongsTo
  {
    return $this->belongsTo(Item::class, 'item_id', 'item_id');
  }

  public function device(): BelongsTo
  {
    return $this->item();
  }
}
