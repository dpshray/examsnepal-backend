<?php

namespace App\Models;

use App\Support\MojibakeFixer;
use App\Traits\SlugTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
class Question extends Model implements HasMedia
{
    use InteractsWithMedia, SlugTrait;

    const QUESTION_IMAGE = 'QUESTION_IMAGE';
    const EXPLANATION_IMAGE = 'EXPLANATION_IMAGE';
    protected $perPage = 10;
    protected $appends = ['image_url', 'explanation_image_url'];

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

    public function examType()
    {
        return $this->belongsTo(ExamType::class, 'exam_type_id');
    }

    public function comments()
    {
        return $this->hasMany(QuestionComment::class);
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
        return $this->hasMany(Answersheet::class,'question_id');
    }
    
    public function image(){
        return $this->morphOne(Image::class,'imagable');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::QUESTION_IMAGE)->singleFile();
        $this->addMediaCollection(self::EXPLANATION_IMAGE)->singleFile();
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(self::QUESTION_IMAGE) ?: null;
    }

    public function getExplanationImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(self::EXPLANATION_IMAGE) ?: null;
    }

    public function getQuestionAttribute(?string $value): ?string
    {
        return MojibakeFixer::fix($value);
    }

    public function getExplanationAttribute(?string $value): ?string
    {
        return MojibakeFixer::fix($value);
    }

    /**
     * SlugTrait reads the column named here. Question text can run to a full
     * paragraph, so we slug from a truncated virtual attribute instead of the
     * raw column to keep URLs reasonable.
     */
    public function slugSource()
    {
        return 'slug_source';
    }

    public function getSlugSourceAttribute(): string
    {
        return Str::words(strip_tags((string) $this->question), 12, '');
    }
}
