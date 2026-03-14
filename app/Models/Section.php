<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $table = 'section';

    protected $fillable = [
        'section_id',
        'course_id',
        'section_name',
        'year_level',
        'semester',
        'school_year',
        'status'
    ];
}
