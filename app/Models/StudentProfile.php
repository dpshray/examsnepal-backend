<?php

namespace App\Models;

use App\Enums\PaymentStatusEnum;
use App\Enums\RequestedFromEnum;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class StudentProfile extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasFactory;

    protected $perPage = 12;
    public $timestamps = false;
    const EMAIL_LINK_EXPIRES_AT = 30; #in minutes
    const PASSWORD_RESET_TOKEN_VALID_UNTIL = 30; #in minutes

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'exam_type_id',
        'date',
        'email_verified_at',
        'fcm_token',
        'requested_from'
    ];

    protected $hidden = ['password'];
    
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'exam_type_id' => 'integer',
            'is_subscripted' => 'integer',
            'requested_from' => RequestedFromEnum::class
        ];
    }

    public static function boot()
    {
        parent::boot();
        static::creating(function ($student) {
            $link_expires_minute   = SELF::EMAIL_LINK_EXPIRES_AT;
            $url_expiration_minute = now()->addMinutes($link_expires_minute);
            $url                   = URL::temporarySignedRoute('student_email_confirmation', $url_expiration_minute, ['email' => $student->email]);

            try {
                Mail::send('mail.student.register', [
                    'name' => $student->name,
                    'url' => $url,
                    'expiration_period' => $url_expiration_minute->format('Y-m-d h:i:s a')
                ], function ($message) use ($student) {
                    $message->to($student->email);
                    $message->subject('New Student Registration');
                });
            } catch (\Exception $e) {
                Log::error($e);
                throw new \Exception('Something went wrong while sending mail');
            }
        });
    }

    public function sendPasswordResetEmail()
    {
        $token = strtoupper(str()->random(5));
        $link_expires_minute   = SELF::PASSWORD_RESET_TOKEN_VALID_UNTIL;
        $url_expiration_minute = now()->addMinutes($link_expires_minute);

        try {
            DB::table('password_reset_tokens')->insert([
                'email' => $this->email,
                'token' => $token,
                'created_at' => now()
            ]);
            Mail::send('mail.student.password_reset', [
                'user' => $this->name,
                'token' => $token,
                'expiration_period' => $url_expiration_minute->format('Y-m-d h:i:s a')
            ], function ($message) {
                $message->to($this->email);
                $message->subject('Student Password Reset');
            });
        } catch (\Exception $e) {
            DB::table('password_reset_tokens')->where('token', $token)->delete();
            $err_message = $e->getMessage();
            Log::error($err_message);
            throw new \Exception($err_message);
        }
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
        return $this->hasMany(Doubt::class, 'student_id');
    }

    public function student_exams()
    {
        return $this->hasMany(StudentExam::class, 'student_id');
    }

    public function exams()
    { # exams completed
        return $this->belongsToMany(Exam::class, 'student_exams', 'student_id', 'exam_id');
    }

    public function forum_questions()
    {
        return $this->hasMany(ForumQuestion::class, 'user_id');
    }

    public function student_pools()
    {
        return $this->hasMany(StudentPool::class, 'student_id');
    }

    public function subscribed()
    {
        return $this->hasOne(Subscriber::class)
            ->where('status', 1)
            ->where('payment_status', PaymentStatusEnum::PAYMENT_SUCCESS->value)
            ->whereDate('end_date', '>=', today())
            ->orderBy('end_date', 'desc');
    }

    public function getIsSubscriptedAttribute()
    {
        return $this->subscribed()->exists() ? 1 : 0;
    }
    public function subscriptions()
    {
        return $this->hasMany(Subscriber::class)
            ->where('status', 1)
            ->where('payment_status', PaymentStatusEnum::PAYMENT_SUCCESS->value)
            ->whereDate('end_date', '>=', today());
    }
    public function examType()
    {
        return $this->belongsTo(ExamType::class, 'exam_type_id');
    }

    function resendEmailVerificationLink() {

        $student = $this;
        $link_expires_minute   = SELF::EMAIL_LINK_EXPIRES_AT;
        $url_expiration_minute = now()->addMinutes($link_expires_minute);
        $url                   = URL::temporarySignedRoute('student_email_confirmation', $url_expiration_minute, ['email' => $student->email]);
        try {
            Mail::send('mail.student.register', [
                'name' => $student->name,
                'url' => $url,
                'expiration_period' => $url_expiration_minute->format('Y-m-d h:i:s a')
            ], function ($message) use ($student) {
                $message->to($student->email);
                $message->subject('Student email verification link');
            });
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception('Something went wrong while sending mail');
        }
    }
}
