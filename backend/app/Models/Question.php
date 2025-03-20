<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Question extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'question',
        'option_1',
        'option_value_1',
        'option_2',
        'option_value_2',
        'option_3',
        'option_value_3',
        'option_4',
        'option_value_4',
        'explanation',
        'subject',
        'exam_type',
        'remark',
        'serial',
        'old_exam_id',
        'uploader',
        'mark_type',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploader');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class, 'question_id');
    }
}
