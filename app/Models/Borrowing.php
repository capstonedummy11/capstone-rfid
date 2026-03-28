<?php

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

  public function student(): BelongsTo
  {
    return $this->belongsTo(Students::class, 'student_id', 'student_id');
  }

  public function instructor(): BelongsTo
  {
    return $this->belongsTo(User::class, 'user_id', 'user_id');
  }

  public function items(): HasMany
  {
    return $this->hasMany(BorrowingItem::class, 'borrowing_id', 'borrowing_id');
  }
}
