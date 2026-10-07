<?php
// FEATURE:console-borrowing - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:borrowing-management - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Borrowing extends Model
{
  protected $table = 'borrowings';
  protected $primaryKey = 'borrowing_id';

  protected $fillable = [
    'student_id',
    'user_id',
    'borrower_type',
    'borrowed_at',
    'returned_at',
    'status',
    'due_date',
    'remarks',
  ];

  protected $casts = [
    'borrowed_at' => 'datetime',
    'returned_at' => 'datetime',
    'due_date' => 'date',
  ];

  // @function student: Ibinabalik ang student Eloquent belongsTo relationship.
  // @useIn student: Eloquent relationship property at eager loading
  public function student(): BelongsTo
  {
    return $this->belongsTo(Students::class, 'student_id', 'student_id');
  }

  // @function instructor: Ibinabalik ang instructor Eloquent belongsTo relationship.
  // @useIn instructor: Eloquent relationship property at eager loading
  public function instructor(): BelongsTo
  {
    return $this->belongsTo(User::class, 'user_id', 'user_id');
  }

  // @function items: Ibinabalik ang items Eloquent hasMany relationship.
  // @useIn items: Eloquent relationship property at eager loading
  public function items(): HasMany
  {
    return $this->hasMany(BorrowingItem::class, 'borrowing_id', 'borrowing_id');
  }
}
