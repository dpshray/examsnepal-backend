<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bookmark extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'question_id',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function question()
    {
        return $this->belongsTo(ForumQuestion::class, 'question_id');
    }


}
