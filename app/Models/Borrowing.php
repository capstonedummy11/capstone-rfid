<?php

namespace App\Models;

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
    'remarks',
  ];

  protected $casts = [
    'borrowed_at' => 'datetime',
    'returned_at' => 'datetime',
  ];

  public function student()
  {
    return $this->belongsTo(Students::class, 'student_id', 'student_id');
  }

  public function instructor()
  {
    return $this->belongsTo(User::class, 'user_id', 'user_id');
  }

  public function items()
  {
    return $this->hasMany(BorrowingItem::class, 'borrowing_id', 'borrowing_id');
  }
}
