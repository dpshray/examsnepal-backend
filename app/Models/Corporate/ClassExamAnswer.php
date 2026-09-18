<?php

namespace App\Models\Corporate;

use App\Models\StudentExam;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClassExamAnswer extends Model
{
    protected $fillable = [
        'student_exam_id',
        'class_exam_question_id',
        'class_exam_question_option_id',
        'answer_text',
        'answer_file_path',
        'is_correct',
        'marks_obtained',
        'graded_at',
        'graded_by',
    ];

    protected $casts = [
        'student_exam_id' => 'integer',
        'class_exam_question_id' => 'integer',
        'class_exam_question_option_id' => 'integer',
        'is_correct' => 'boolean',
        'marks_obtained' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    public function studentExam()
    {
        return $this->belongsTo(StudentExam::class, 'student_exam_id');
    }

    public function question()
    {
        return $this->belongsTo(ClassExamQuestion::class, 'class_exam_question_id');
    }

    public function selectedOption()
    {
        return $this->belongsTo(ClassExamQuestionOption::class, 'class_exam_question_option_id');
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
