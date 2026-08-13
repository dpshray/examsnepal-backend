<?php

namespace App\Models\Corporate;

use App\Models\InstituteStudent;
use Illuminate\Database\Eloquent\Model;

class ClassStudent extends Model
{
    protected $table = 'class_students';

    protected $fillable = [
        'class_id',
        'institute_student_id',
        'status',
    ];

    protected $casts = [
        'class_id' => 'integer',
        'institute_student_id' => 'integer',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function student()
    {
        return $this->belongsTo(InstituteStudent::class, 'institute_student_id');
    }
}
