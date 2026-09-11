<?php

namespace App\Models\Corporate;

use App\Models\Exam;
use Illuminate\Database\Eloquent\Model;

class ClassExamSection extends Model
{
    protected $fillable = ['exam_id', 'title', 'detail', 'order'];

    protected $casts = [
        'exam_id' => 'integer',
        'order' => 'integer',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function questions()
    {
        return $this->hasMany(ClassExamQuestion::class, 'class_exam_section_id')->orderBy('order');
    }
}
