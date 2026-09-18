<?php

namespace App\Models\Corporate;

use App\Models\InstituteStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClassAssignmentSubmission extends Model
{
    protected $fillable = [
        'class_assignment_id',
        'institute_student_id',
        'type',
        'content_text',
        'file_path',
        'annotated_file_path',
        'score',
        'remark',
        'graded_at',
        'graded_by',
    ];

    protected $casts = [
        'class_assignment_id' => 'integer',
        'institute_student_id' => 'integer',
        'score' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(ClassAssignment::class, 'class_assignment_id');
    }

    public function student()
    {
        return $this->belongsTo(InstituteStudent::class, 'institute_student_id');
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
