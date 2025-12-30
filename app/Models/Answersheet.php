<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Answersheet extends Model
{
    //
    use HasFactory;

    protected $perPage = 10;

    protected $fillable = [
        'student_exam_id',
        'question_id',
        'selected_option_id',
        'is_correct'
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'student_exam_id' => 'integer',
            'question_id' => 'integer',
            'selected_option_id' => 'integer',
            'is_correct' => 'integer'
        ];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class);
    }

    function student_exam() {
        return $this->belongsTo(StudentExam::class);
    }
}
