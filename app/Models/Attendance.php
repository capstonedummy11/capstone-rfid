<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    protected $fillable = [
        'attendance_id',
        'student_id',
        'date',
        'time_in',
        'time_out',
        'status',
        'subject',
        'room'
    ];
}
