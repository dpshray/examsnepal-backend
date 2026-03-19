<?php

namespace App\Models\Blog;

use Illuminate\Database\Eloquent\Model;

class CategoriesInBlog extends Model
{
    //
    protected $fillable = [
        'blog_id',
        'blog_category_id',
    ];
    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
    public function blogCategory()
    {
        return $this->belongsTo(BlogCategories::class);
    }
}
