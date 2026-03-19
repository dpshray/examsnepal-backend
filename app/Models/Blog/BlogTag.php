<?php

namespace App\Models\Blog;

use App\Traits\SlugTrait;
use Illuminate\Database\Eloquent\Model;

class BlogTag extends Model
{
    //
    use SlugTrait;
    protected $fillable = [
        'title',
        'slug',
    ];
    public function slugSource()
    {
        return 'title';
    }
}
