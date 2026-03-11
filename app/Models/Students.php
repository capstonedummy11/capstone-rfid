<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Students extends Model
{
    use HasFactory;

    protected $table = 'students';

    protected $fillable = [
        'student_id',
        'section_id',
        'course_id',
        'student_number',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'email',
        'phone',
        'year_level',
        'semester',
        'school_year',
        'rfid_tag',
        'status'
    ];
}
