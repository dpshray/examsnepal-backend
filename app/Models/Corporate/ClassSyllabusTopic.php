<?php

namespace App\Models\Corporate;

use Illuminate\Database\Eloquent\Model;

class ClassSyllabusTopic extends Model
{
    protected $fillable = [
        'class_id',
        'title',
        'description',
        'order',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'order' => 'integer',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }
}
