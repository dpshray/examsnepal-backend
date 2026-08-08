<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionComment extends Model
{
    protected $fillable = [
        'question_id',
        'name',
        'comment',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
