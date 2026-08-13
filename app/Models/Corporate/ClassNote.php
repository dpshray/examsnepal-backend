<?php

namespace App\Models\Corporate;

use Illuminate\Database\Eloquent\Model;

class ClassNote extends Model
{
    protected $fillable = [
        'class_id',
        'title',
        'type',
        'content',
        'file_path',
        'video_url',
    ];

    protected $casts = [
        'class_id' => 'integer',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }
}
