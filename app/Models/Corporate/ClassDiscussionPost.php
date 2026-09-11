<?php

namespace App\Models\Corporate;

use Illuminate\Database\Eloquent\Model;

class ClassDiscussionPost extends Model
{
    protected $fillable = [
        'class_id',
        'author_id',
        'author_type',
        'content',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'author_id' => 'integer',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function author()
    {
        return $this->morphTo();
    }

    public function replies()
    {
        return $this->hasMany(ClassDiscussionReply::class)->oldest();
    }
}
