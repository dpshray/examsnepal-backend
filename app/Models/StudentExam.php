<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentExam extends Model
{
    protected $fillable = [
        'exam_id',
        'student_id',
        'first_time_token'
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'exam_id' => 'integer',
            'student_id' => 'integer'
        ];
    }

    public function answers()
    {
        return $this->hasMany(Answersheet::class, 'student_exam_id');
    }

    public function correct_answers()
    {
        return $this->hasMany(Answersheet::class)->where('is_correct', 1);
    }

    function student()
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function incorrect_answers()
    {
        return $this->hasMany(Answersheet::class)
            ->where('is_correct', false);
    }
}
