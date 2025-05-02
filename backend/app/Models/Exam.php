<?php
namespace App\Models;

use App\Enums\ExamTypeEnum;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Exam extends Model
{
    public $timestamps = false;
    /**
     * the column name of this table/model indicates this exams another type which is described in ExamTypeEnum::class
     *
     */

    protected $fillable = [
        'user_id', # added_by
        'exam_type_id',
        'exam_name',
        'status',
        'description',
        'exam_date',
        'exam_time',
        'is_active',
        'price',
        'assign_id',# ID belongs to users table
        'is_question_bank',
    ];

    public function scopeFreeType(Builder $query): Builder
    {
        return $query->where('status', ExamTypeEnum::FREE_QUIZ->value);
    }

    public function scopeSprintType(Builder $query): Builder
    {
        return $query->where('status', ExamTypeEnum::SPRINT_QUIZ->value);
    }

    public function scopeMockType(Builder $query): Builder
    {
        return $query->where('status', ExamTypeEnum::MOCK_TEST->value);
    }

    public function scopeAuthUserPending(Builder $query): Builder
    {
        return $this->completedPendingQuery($query)
                ->whereDoesntHave('student_exams', fn($qry) => $qry->where('student_id', Auth::guard('api')->id()))
                ->when(Auth::guard('api')->check(), fn($qry) => $qry->where('exam_type_id', Auth::guard('api')->user()->exam_type_id)) # for student load specific exams
                ->where('live',1)
                ->orderBy('id','DESC'); 
    }

    public function scopeAuthUserCompleted(Builder $query): Builder
    {
        return $this->completedPendingQuery($query)
            ->with([
                'student_exams' => fn($qry) => $qry->select(['id', 'student_id', 'exam_id'])->with([
                    'student:id,name',
                    'answers' => fn($q) => $q->select('student_exam_id', 'is_correct')->where('is_correct', 1),
                ])
                    ->withCount([
                        'answers as correct_answers_count' => fn($q) => $q->where('is_correct', 1),
                    ])
                    ->orderBy('correct_answers_count', 'DESC'),
            ])
            ->whereHas('exams', fn($qry) => $qry->where('student_id', Auth::guard('api')->id()))
            ->when(Auth::guard('api')->check(), fn($qry) => $qry->where('exam_type_id', Auth::guard('api')->user()->exam_type_id)) # for student load specific exams
            ->where('live',1)
            ->orderBy('id','DESC'); 
    }

    private function completedPendingQuery(Builder $query): Builder
    {
        return $query->select(['id', 'exam_name', 'status', 'user_id'])->with('user:id,fullname')->withCount('questions');
    }

    

    public function exams()
    {
        return $this->belongsToMany(StudentProfile::class, 'student_exams', 'exam_id', 'student_id');
    }

    public function student_exams()
    {
        return $this->hasMany(StudentExam::class);
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
}
