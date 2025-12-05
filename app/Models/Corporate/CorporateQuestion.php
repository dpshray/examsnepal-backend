<?php

namespace App\Models\Corporate;

use Illuminate\Database\Eloquent\Model;

class CorporateQuestion extends Model
{
    //
    protected $fillable = [
        'corporate_exam_id',
        'corporate_exam_section_id',
        'question',
        'description',
        'is_negative_marking',
        'negative_mark',
        'full_marks',
        'question_type',
    ];

    public function exam()
    {
        return $this->belongsTo(CorporateExam::class, 'corporate_exam_id');
    }

    public function section()
    {
        return $this->belongsTo(CorporateExamSection::class, 'corporate_exam_section_id');
    }
}
