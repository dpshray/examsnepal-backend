<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class StudentProfile extends Authenticatable implements JWTSubject
{
    use HasFactory;
    public $timestamps = false;
    const EMAIL_LINK_EXPIRES_AT = 25;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'exam_type_id',
        'date',
        'email_verified_at'
    ];

    protected $hidden = ['password'];


    public static function boot()
    {
        parent::boot();
        static::creating(function ($student) {
            $token    = Str::random(64);

            $link_expires_minute   = SELF::EMAIL_LINK_EXPIRES_AT;
            $url_expiration_minute = now()->addMinutes($link_expires_minute);
            $url                   = URL::temporarySignedRoute('student_email_confirmation', $url_expiration_minute, ['token' => $token]);
            // Log::info("url {$url}");
            Mail::send('mail.student.register', [
                'name' => $student->name, 
                'url' => $url, 
                'expiration_period' => $url_expiration_minute->format('Y-m-d h:i:s a')
            ], function ($message) use ($student) {
                $message->to($student->email);
                $message->subject('New Student Registration');
            });

            DB::table('password_reset_tokens')->insert([
                'email'      => $student->email,
                'token'      => $token,
                'created_at' => now(),
            ]);
        });
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

    public function student_pools(){
        return $this->hasMany(StudentPool::class,'student_id');
    }
}
