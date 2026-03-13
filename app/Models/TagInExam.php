<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagInExam extends Model
{
    //
    protected $fillable = [
        'exam_id',
        'tag_id',
    ];
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }
}
