<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Students extends Model
{
    use HasFactory;

    protected $table = 'students';
    protected $primaryKey = 'student_id';

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

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }
}
