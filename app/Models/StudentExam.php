<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class StudentExam extends Model
{
    protected $fillable = [
        'exam_id',
        'student_id',
        'institute_student_id',
        'first_time_token',
        'is_exam_completed'
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'exam_id' => 'integer',
            'student_id' => 'integer',
            'institute_student_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (StudentExam $model) {
            if (is_null($model->student_id) === is_null($model->institute_student_id)) {
                throw new \RuntimeException('StudentExam must belong to exactly one of student_id or institute_student_id.');
            }
        });
    }

    protected function source(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->institute_student_id !== null ? 'institute' : 'app',
        );
    }

    public function answers()
    {
        return $this->hasMany(Answersheet::class, 'student_exam_id');
    }

    public function correct_answers()
    {
        return $this->hasMany(Answersheet::class)->where('is_correct', 1);
    }

    function student()
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    function institute_student()
    {
        return $this->belongsTo(InstituteStudent::class, 'institute_student_id');
    }

    function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function incorrect_answers()
    {
        return $this->hasMany(Answersheet::class)
            ->where('is_correct', false);
    }
}
