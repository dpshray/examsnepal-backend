<?php

namespace App\Models;

use App\Traits\SlugTrait;
use Illuminate\Database\Eloquent\Model;

class ExamTag extends Model
{
    //
    use SlugTrait;
    protected $fillable = [
        'name',
        'slug',
    ];
    public function exams()
    {
        return $this->belongsToMany(
            Exam::class,
            'tag_in_exams',
            'tag_id',
            'exam_id'
        )->withTimestamps();
    }
    public function slugSource()
    {
        return 'name';
    }
}
