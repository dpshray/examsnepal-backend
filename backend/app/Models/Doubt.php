<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Doubt extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'email',
        'exam_id',
        'student_id',
        'question_id',
        'org_id',
        'doubt',
        'date',
        'remarks',
        'status',
        'solved_by',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function organization()
    {
        return $this->belongsTo(User::class, 'org_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

}
