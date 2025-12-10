<?php

namespace App\Models\Corporate;

use Illuminate\Database\Eloquent\Concerns\HasEvents;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CorporateQuestion extends Model implements HasMedia
{
    //
    use InteractsWithMedia, HasEvents;
    const QUESTION_IMAGE = 'QUESTION_IMAGE';
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
    protected $casts = [
    'is_negative_marking' => 'boolean',
    // 'options' => 'array',
];

    public function exam()
    {
        return $this->belongsTo(CorporateExam::class, 'corporate_exam_id');
    }

    public function section()
    {
        return $this->belongsTo(CorporateExamSection::class, 'corporate_exam_section_id');
    }
    public function options(){
        return $this->hasMany(CorporateQuestionOption::class,'corporate_question_id');
    }
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::QUESTION_IMAGE)->singleFile();
    }
}
