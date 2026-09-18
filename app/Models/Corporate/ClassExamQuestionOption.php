<?php

namespace App\Models\Corporate;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ClassExamQuestionOption extends Model implements HasMedia
{
    use InteractsWithMedia;

    const OPTION_IMAGE = 'OPTION_IMAGE';

    protected $fillable = ['class_exam_question_id', 'option', 'value', 'order'];

    protected $casts = [
        'class_exam_question_id' => 'integer',
        'value' => 'boolean',
        'order' => 'integer',
    ];

    protected $appends = ['image_url'];

    public function question()
    {
        return $this->belongsTo(ClassExamQuestion::class, 'class_exam_question_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(self::OPTION_IMAGE) ?: null;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::OPTION_IMAGE)->singleFile();
    }
}
