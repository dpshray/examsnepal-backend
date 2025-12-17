<?php

namespace App\Models;

use App\Models\Corporate\CorporateQuestion;
use App\Models\Corporate\CorporateQuestionOption;
use Illuminate\Database\Eloquent\Model;

class StudentAnswer extends Model
{
    //
    protected $fillable = [
        'exam_attempts_id', 'question_id', 'options_id',
        'subjective_answer', 'marks_obtained'
    ];

    protected $casts = [
        'marks_obtained' => 'decimal:2',
    ];

    public function examAttempt()
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempts_id');
    }

    public function question()
    {
        return $this->belongsTo(CorporateQuestion::class, 'question_id');
    }

    public function option()
    {
        return $this->belongsTo(CorporateQuestionOption::class, 'options_id');
    }
}
