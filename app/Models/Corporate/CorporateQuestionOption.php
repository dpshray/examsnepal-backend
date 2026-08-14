<?php

namespace App\Models\Corporate;

use App\Support\MojibakeFixer;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CorporateQuestionOption extends Model implements HasMedia
{
    use InteractsWithMedia;

    const OPTION_IMAGE = 'OPTION_IMAGE';

    //
    protected $fillable = ['corporate_question_id', 'option', 'value'];
    protected $casts = [
        'value'=>'integer'
    ];

    protected $appends = ['image_url'];

    public function getOptionAttribute(?string $value): ?string
    {
        return MojibakeFixer::fix($value);
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
