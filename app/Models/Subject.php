<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
  use HasFactory;

  protected $table = 'subjects';
  protected $primaryKey = 'subject_id';

  protected $fillable = [
    'subject_id',
    'section_id',
    'user_id',
    'subject_name',
    'subject_code',
    'year_level',
    'department',
    'unit',
    'semester',
  ];
}
