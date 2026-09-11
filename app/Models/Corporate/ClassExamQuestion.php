<?php

namespace App\Models\Corporate;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ClassExamQuestion extends Model implements HasMedia
{
    use InteractsWithMedia;

    const QUESTION_IMAGE = 'QUESTION_IMAGE';

    protected $fillable = [
        'class_exam_section_id',
        'question_type',
        'question',
        'description',
        'full_marks',
        'is_negative_marking',
        'negative_mark',
        'order',
    ];

    protected $casts = [
        'class_exam_section_id' => 'integer',
        'full_marks' => 'decimal:2',
        'is_negative_marking' => 'boolean',
        'negative_mark' => 'decimal:2',
        'order' => 'integer',
    ];

    public function section()
    {
        return $this->belongsTo(ClassExamSection::class, 'class_exam_section_id');
    }

    public function options()
    {
        return $this->hasMany(ClassExamQuestionOption::class, 'class_exam_question_id')->orderBy('order');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::QUESTION_IMAGE)->singleFile();
    }
}
