<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    //

    protected $fillable = [
        'user_id',
        'exam_type_id',
        'exam_name',
        'status',
        'description',
        'exam_date',
        'exam_time',
        'is_active',
        'price',
        'assign_id',
        'is_question_bank',
    ];
    

    public function organization()
    {
        return $this->belongsTo(Organization::class); // Each Exam belongs to an Organization
    }

    public function examType()
    {
        return $this->belongsTo(ExamType::class); // Each Exam belongs to an Exam Type
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id'); // Defines the relationship
    }

    public function doubts()
    {
        return $this->hasMany(Doubt::class);
    }
}
