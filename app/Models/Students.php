<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'status',
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

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'student_id', 'student_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'student_id', 'student_id');
    }

    public function currentEnrollment(): ?StudentEnrollment
    {
        $activeYearId = AcademicYear::currentOrLatest()?->academic_year_id;

        if ($activeYearId) {
            return $this->enrollments()
                ->where('academic_year_id', $activeYearId)
                ->orderByDesc('student_enrollment_id')
                ->first();
        }

        return $this->enrollments()
            ->latest('student_enrollment_id')
            ->first();
    }

    public function parentUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student_links', 'student_id', 'parent_user_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function excuseLetters(): HasMany
    {
        return $this->hasMany(StudentExcuseLetter::class, 'student_id', 'student_id');
    }

    public function portalMessages(): HasMany
    {
        return $this->hasMany(StudentPortalMessage::class, 'student_id', 'student_id');
    }
}
