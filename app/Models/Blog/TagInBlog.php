<?php

namespace App\Models\Blog;

use Illuminate\Database\Eloquent\Model;

class TagInBlog extends Model
{
    //
    protected $fillable = [
        'blog_id',
        'blog_tag_id',
    ];
    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
    public function blogTag()
    {
        return $this->belongsTo(BlogTag::class);
    }
}
