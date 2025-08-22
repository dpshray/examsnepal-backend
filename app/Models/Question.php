<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Question extends Model
{
    protected $perPage = 10;

    public $timestamps = false;
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'question',
        'added_by',
        'exam_type_id',
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
        'from_question_bank',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'exam_id' => 'integer',
            'exam_type_id' => 'integer',
            'added_by' => 'integer',
            'subject_id' => 'integer',
        ];
    }

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
    public function doubts()
    {
        return $this->hasMany(Doubt::class, 'question_id');
    }

    public function options(){
        return $this->hasMany(OptionQuestion::class);
    }

    public function student_answers() {
        return $this->hasMany(Answersheet::class);
    }
    
    public function image(){
        return $this->morphOne(Image::class,'imagable');
    }
}
