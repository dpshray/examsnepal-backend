<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentExam extends Model
{
    protected $fillable = [
        'exam_id',
        'student_id',
        'completed',
        'first_time_token'
    ];

    public function answers(){
        return $this->hasMany(Answersheet::class,'student_exam_id');
    }

    function student() {
        return $this->belongsTo(StudentProfile::class,'student_id');
    }

    function exams() {
        return $this->hasMany(Exam::class);
    }
}
