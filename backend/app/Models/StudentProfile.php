<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'exam_type_id'
    ];

    protected $hidden = ['password'];

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
    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class, 'student_id');
    }

    public function doubts()
    {
        return $this->hasMany(Doubt::class,'student_id');
    }

    public function student_exams(){
        return $this->hasMany(StudentExam::class,'student_id');
    }

    public function exams(){ # exams completed
        return $this->belongsToMany(Exam::class,'student_exams','student_id','exam_id');
    }

    public function forum_questions() {
        return $this->hasMany(ForumQuestion::class,'user_id');
    }
}
