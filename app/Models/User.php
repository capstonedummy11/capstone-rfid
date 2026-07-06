<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'role',
        'is_root_admin',
        'phone',
        'gender',
        'rfid_tag',
        'face_images',
        'security_question',
        'security_answer_hash',
        'security_questions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        /* 'two_factor_secret',
        'two_factor_recovery_codes', */
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_root_admin' => 'boolean',
            'face_images' => 'array',
            'security_questions' => 'array',
        ];
    }

    /**
     * Get the instructor associated with the user
     */
    public function instructor(): HasOne
    {
        return $this->hasOne(Instructor::class, 'user_id', 'user_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'user_id', 'user_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id', 'user_id');
    }

    public function linkedStudents(): BelongsToMany
    {
        return $this->belongsToMany(Students::class, 'parent_student_links', 'parent_user_id', 'student_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }
}
