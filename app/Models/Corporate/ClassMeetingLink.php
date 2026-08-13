<?php

namespace App\Models\Corporate;

use Illuminate\Database\Eloquent\Model;

class ClassMeetingLink extends Model
{
    protected $fillable = [
        'class_id',
        'title',
        'url',
        'scheduled_at',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'scheduled_at' => 'datetime',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }
}
