<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYearRolloverItem extends Model
{
    protected $primaryKey = 'academic_year_rollover_item_id';
    protected $fillable = ['academic_year_rollover_id', 'student_id', 'source_student_enrollment_id', 'destination_student_enrollment_id', 'destination_section_id', 'decision', 'status', 'message', 'payload'];
    protected $casts = ['payload' => 'array'];
}
