<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Answersheet extends Model
{
    //
    use HasFactory;


    protected $fillable = [
        'exam_id',
        'question_id',
        'student_id',
        'correct_answer_submitted',
        'choosed_option_value',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class);
    }
}
