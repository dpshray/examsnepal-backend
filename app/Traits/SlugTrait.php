<?php

namespace App\Traits;
use Illuminate\Support\Str;
trait SlugTrait
{
    //
    public static function bootSlugTrait()
    {
        static::creating(function ($model) {
            $column = $model->slugSource();
            $model->slug = static::createSlug($model, $model->$column);
        });

        static::updating(function ($model) {
            $column = $model->slugSource();
            if ($model->isDirty($column)) {
                $model->slug = static::createSlug($model, $model->$column);
            }
        });
    }

    public static function createSlug($model, $text)
    {
        $slug = Str::slug($text);

        // Ensure unique slug
        $count = static::where('slug', 'LIKE', "{$slug}%")
            ->where('id', '!=', $model->id ?? 0)
            ->count();

        return $count ? "{$slug}-{$count}" : $slug;
    }

    // Each model MUST override this to tell which column to use
    abstract public function slugSource();
}
