<?php

namespace App\Models\Corporate;

use App\Models\Exam;
use App\Models\InstituteStudent;
use App\Models\User;
use App\Traits\SlugTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classroom extends Model
{
    use SoftDeletes, SlugTrait;

    protected $table = 'classes';

    protected $fillable = [
        'institute_id',
        'name',
        'slug',
        'target',
        'bio',
        'price',
        'duration_days',
        'syllabus',
    ];

    protected $casts = [
        'institute_id' => 'integer',
        'price' => 'decimal:2',
        'duration_days' => 'integer',
    ];

    public function slugSource()
    {
        return 'name';
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function institute()
    {
        return $this->belongsTo(User::class, 'institute_id');
    }

    public function notes()
    {
        return $this->hasMany(ClassNote::class, 'class_id');
    }

    public function meetingLinks()
    {
        return $this->hasMany(ClassMeetingLink::class, 'class_id');
    }

    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'class_exams', 'class_id', 'exam_id');
    }

    public function students()
    {
        return $this->belongsToMany(InstituteStudent::class, 'class_students', 'class_id', 'institute_student_id')
            ->withPivot('id', 'status')
            ->withTimestamps();
    }
}
