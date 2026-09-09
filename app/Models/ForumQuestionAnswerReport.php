<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumQuestionAnswerReport extends Model
{
    //
    protected $fillable = [
        'forum_question_id',
        'forum_answer_id',
        'reason',
        'report_type',
        'student_id',
    ];

    function question()
    {
        return $this->belongsTo(ForumQuestion::class, 'forum_question_id');
    }

    function answer()
    {
        return $this->belongsTo(ForumAnswer::class, 'forum_answer_id');
    }

    function student()
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }
}
