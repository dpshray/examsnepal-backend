<?php

namespace App\Models;

use App\Enums\ExamTypeEnum;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    /**
     * the column name of this table/model indicates this exams another type which is described in ExamTypeEnum::class
     * 
     */

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

    function scopeFreeType(Builder $query) : Builder {
        return $query->where('status', ExamTypeEnum::FREE_QUIZ->value);
    }    
    
    function scopeSprintType(Builder $query) : Builder {
        return $query->where('status', ExamTypeEnum::SPRINT_QUIZ->value);
    }    
    
    function scopeMockType(Builder $query) : Builder {
        return $query->where('status', ExamTypeEnum::MOCK_TEST->value);
    }

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
    /**
     * this actually should be renamed as added_by_id
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id'); // Defines the relationship
    }

    public function doubts()
    {
        return $this->hasMany(Doubt::class);
    }
    public function questions()
    {
        return $this->hasMany(Question::class);
    }
    public function answerSheets()
    {
        return $this->hasMany(AnswerSheet::class);
    }

    public function players(){
        return $this->hasMany(StudentExam::class);
    }
}
