<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstituteReview extends Model
{
    protected $fillable = [
        'institute_id',
        'institute_student_id',
        'rating',
        'comment',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'institute_id' => 'integer',
            'institute_student_id' => 'integer',
            'rating' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function institute()
    {
        return $this->belongsTo(User::class, 'institute_id');
    }

    public function student()
    {
        return $this->belongsTo(InstituteStudent::class, 'institute_student_id');
    }
}
