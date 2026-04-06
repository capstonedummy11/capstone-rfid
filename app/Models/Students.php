<?php

namespace App\Models;

use App\Models\AttendanceLog;
use App\Models\Borrowing;
use App\Models\Section;
use App\Models\Strand;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Students extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'students';
    protected $primaryKey = 'student_id';

    protected $fillable = [
        'student_id',
        'section_id',
        'strand_id',
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
        'face_images',
        'status'
    ];

    protected $casts = [
        'face_images' => 'array',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }


    public function strand(): BelongsTo
    {
        return $this->belongsTo(Strand::class, 'strand_id', 'strand_id');
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class, 'student_id', 'student_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'student_id', 'student_id');
    }
}
