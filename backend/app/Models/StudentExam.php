<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentExam extends Model
{
    protected $fillable = [
        'exam_id',
        'student_id',
        'completed'
    ];

    public function answers(){
        return $this->hasMany(Answersheet::class,'student_exam_id');
    }
}
