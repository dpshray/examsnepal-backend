<?php

namespace App\Models;

use App\Support\MojibakeFixer;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class OptionQuestion extends Model implements HasMedia
{
    use InteractsWithMedia;

    const OPTION_IMAGE = 'OPTION_IMAGE';

    public $timestamps = false;
    protected $fillable = [
        'option',
        'value'
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'question_id' => 'integer',
            'value' => 'integer',
        ];
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(self::OPTION_IMAGE) ?: null;
    }

    public function getOptionAttribute(?string $value): ?string
    {
        return MojibakeFixer::fix($value);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::OPTION_IMAGE)->singleFile();
    }
}
