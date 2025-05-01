<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class Bookmark extends Model
{
    //
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        // 'exam_id',
        'student_id',
        'question_id',
    ];

    // public function exam()
    // {
    //     return $this->belongsTo(Exam::class);
    // }

    public function student()
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function questions()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }    

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public static function boot()
    {
        parent::boot();
        static::creating(function ($bookmark) {
            $bookmark->student_id = Auth::guard('api')->id();
            $bookmark->date = now()->format('Y-m-d');

        });
    }

}
