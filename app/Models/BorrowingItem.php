<?php
// FEATURE:console-borrowing - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:borrowing-management - konektadong model, service, route, o UI para sa feature na ito.

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

  // @function borrowing: Ibinabalik ang borrowing Eloquent belongsTo relationship.
  // @useIn borrowing: Eloquent relationship property at eager loading
  public function borrowing(): BelongsTo
  {
    return $this->belongsTo(Borrowing::class, 'borrowing_id', 'borrowing_id');
  }

  // @function item: Ibinabalik ang item Eloquent belongsTo relationship.
  // @useIn item: Eloquent relationship property at eager loading
  public function item(): BelongsTo
  {
    return $this->belongsTo(Item::class, 'item_id', 'item_id');
  }

  // @function device: Ibinabalik ang device Eloquent relationship.
  // @useIn device: Eloquent relationship property at eager loading
  public function device(): BelongsTo
  {
    return $this->item();
  }
}
