<?php

namespace App\Models\Blog;

use App\Traits\SlugTrait;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Blog extends Model implements HasMedia
{

    use InteractsWithMedia, SlugTrait;
    const BLOG_IMAGE = 'BLOG_IMAGE';
    protected $fillable = [
        'title',
        'slug',
        'content',
        'author',
        'published_date',
        'summary',
        'status',
        'cta_text',
        'cta_link',
    ];
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::BLOG_IMAGE)->singleFile();
    }
    public function category()
    {
        return $this->belongsToMany(BlogCategories::class, 'categories_in_blogs', 'blog_id', 'blog_category_id');
    }
    public function tag()
    {
        return $this->belongsToMany(BlogTag::class, 'tag_in_blogs', 'blog_id', 'blog_tag_id');
    }
    public function slugSource()
    {
        return 'title';
    }
}
