<?php
// FEATURE:student-management - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:student-rfid-enrollment - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:student-face-enrollment - konektadong model, service, route, o UI para sa feature na ito.

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

    // @function section: Ibinabalik ang section Eloquent belongsTo relationship.
    // @useIn section: Eloquent relationship property at eager loading
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }

    // @function strand: Ibinabalik ang strand Eloquent belongsTo relationship.
    // @useIn strand: Eloquent relationship property at eager loading
    public function strand(): BelongsTo
    {
        return $this->belongsTo(Strand::class, 'strand_id', 'strand_id');
    }

    // @function borrowings: Ibinabalik ang borrowings Eloquent hasMany relationship.
    // @useIn borrowings: Eloquent relationship property at eager loading
    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class, 'student_id', 'student_id');
    }

    // @function attendanceLogs: Ibinabalik ang attendance logs Eloquent hasMany relationship.
    // @useIn attendanceLogs: Eloquent relationship property at eager loading
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'student_id', 'student_id');
    }

    // @function attendances: Ibinabalik ang attendances Eloquent hasMany relationship.
    // @useIn attendances: Eloquent relationship property at eager loading
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'student_id', 'student_id');
    }

    // @function enrollments: Ibinabalik ang enrollments Eloquent hasMany relationship.
    // @useIn enrollments: Eloquent relationship property at eager loading
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'student_id', 'student_id');
    }

    // @function currentEnrollment: Kinukuha ang current enrollment result para sa Students.
    // @useIn currentEnrollment: app/Http/Controllers/StudentsController.php
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

    // @function parentUsers: Ibinabalik ang parent users Eloquent belongsToMany relationship.
    // @useIn parentUsers: Eloquent relationship property at eager loading
    public function parentUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student_links', 'student_id', 'parent_user_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    // @function excuseLetters: Ibinabalik ang excuse letters Eloquent hasMany relationship.
    // @useIn excuseLetters: Eloquent relationship property at eager loading
    public function excuseLetters(): HasMany
    {
        return $this->hasMany(StudentExcuseLetter::class, 'student_id', 'student_id');
    }

    // @function portalMessages: Ibinabalik ang portal messages Eloquent hasMany relationship.
    // @useIn portalMessages: Eloquent relationship property at eager loading
    public function portalMessages(): HasMany
    {
        return $this->hasMany(StudentPortalMessage::class, 'student_id', 'student_id');
    }
}
