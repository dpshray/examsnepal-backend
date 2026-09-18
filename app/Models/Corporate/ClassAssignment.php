<?php

namespace App\Models\Corporate;

use Illuminate\Database\Eloquent\Model;

class ClassAssignment extends Model
{
    protected $fillable = [
        'class_id',
        'title',
        'type',
        'content_text',
        'file_path',
        'full_marks',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'full_marks' => 'decimal:2',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function submissions()
    {
        return $this->hasMany(ClassAssignmentSubmission::class, 'class_assignment_id');
    }
}
