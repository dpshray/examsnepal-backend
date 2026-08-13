<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class InstituteStudent extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $fillable = [
        'institute_id',
        'name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'institute_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // Required for JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // Required for JWT
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function institute()
    {
        return $this->belongsTo(User::class, 'institute_id');
    }

    public function review()
    {
        return $this->hasOne(InstituteReview::class);
    }

    public function classes()
    {
        return $this->belongsToMany(
            \App\Models\Corporate\Classroom::class,
            'class_students',
            'institute_student_id',
            'class_id'
        )->withPivot('id', 'status')->withTimestamps();
    }

    public function studentExams()
    {
        return $this->hasMany(StudentExam::class, 'institute_student_id');
    }
}
