<?php
// FEATURE:authentication - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:user-management - konektadong model, service, route, o UI para sa feature na ito.
// FEATURE:parent-student-view - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $primaryKey = 'user_id';

    protected $appends = [
        'profile_photo_url',
    ];

    // @function getIdAttribute: Kinukuha ang id attribute sa User flow.
    // @useIn getIdAttribute: Eloquent attribute read/write lifecycle
    public function getIdAttribute(): ?int
    {
        return $this->getKey();
    }

    protected $fillable = [
        'name',
        'middle_name',
        'last_name',
        'email',
        'email_verified_at',
        'password',
        'must_change_password',
        'role',
        'is_root_admin',
        'phone',
        'gender',
        'rfid_tag',
        'face_images',
        'security_question',
        'security_answer_hash',
        'security_questions',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'profile_photo_path',
    ];

    // @function casts: Ibinabalik ang field casts ng User model.
    // @useIn casts: Eloquent attribute casting lifecycle
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'is_root_admin' => 'boolean',
            'must_change_password' => 'boolean',
            'face_images' => 'array',
            'security_questions' => 'array',
        ];
    }

    // @function getProfilePhotoUrlAttribute: Kinukuha ang profile photo url attribute sa User flow.
    // @useIn getProfilePhotoUrlAttribute: Eloquent attribute read/write lifecycle
    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path
            ? Storage::disk('public')->url($this->profile_photo_path)
            : null;
    }

    // @function instructor: Ibinabalik ang instructor Eloquent hasOne relationship.
    // @useIn instructor: Eloquent relationship property at eager loading
    /**
     * Get the instructor associated with the user
     */
    public function instructor(): HasOne
    {
        return $this->hasOne(Instructor::class, 'user_id', 'user_id');
    }

    // @function subjects: Ibinabalik ang subjects Eloquent hasMany relationship.
    // @useIn subjects: Eloquent relationship property at eager loading
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'user_id', 'user_id');
    }

    // @function activityLogs: Ibinabalik ang activity logs Eloquent hasMany relationship.
    // @useIn activityLogs: Eloquent relationship property at eager loading
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id', 'user_id');
    }

    // @function linkedStudents: Ibinabalik ang linked students Eloquent belongsToMany relationship.
    // @useIn linkedStudents: Eloquent relationship property at eager loading
    public function linkedStudents(): BelongsToMany
    {
        return $this->belongsToMany(Students::class, 'parent_student_links', 'parent_user_id', 'student_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }
}
