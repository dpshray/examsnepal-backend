<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $perPage = 12;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'fullname',
        'role',
        'role_id',
        'created_by',
        'created_date',
        'user',
        'image',
        'about',
        'email',
        'phone',
        'location',
        'facebook',
        'twitter',
        'linkedin',
        'org',
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // Required for JWT
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'role_id' => 'integer',
            'added_by' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function doubts()
    {
        return $this->hasMany(Doubt::class, 'org_id');
    }

    public function answerSheets()
    {
        return $this->hasMany(AnswerSheet::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function isTeacher()
    {
        return $this->role->name == RoleEnum::TEACHER->value;
    }

    public function teacherExams()
    {
        return $this->hasMany(Exam::class);
    }
    public function isAdmin()
    {
        return $this->role->name == RoleEnum::ADMIN->value;
    }
}
