<?php

namespace App\Models\Corporate;

use Illuminate\Database\Eloquent\Model;

class ClassDiscussionReply extends Model
{
    protected $fillable = [
        'class_discussion_post_id',
        'author_id',
        'author_type',
        'content',
    ];

    protected $casts = [
        'class_discussion_post_id' => 'integer',
        'author_id' => 'integer',
    ];

    public function post()
    {
        return $this->belongsTo(ClassDiscussionPost::class, 'class_discussion_post_id');
    }

    public function author()
    {
        return $this->morphTo();
    }
}
