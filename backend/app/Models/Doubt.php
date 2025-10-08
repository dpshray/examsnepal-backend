<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class Doubt extends Model
{
    //
    use HasFactory;
    protected $perPage = 12;

    protected $fillable = [
        'email',
        'exam_id',
        'student_id',
        'question_id',
        'org_id',
        'doubt',
        'date',
        'remark',
        'status',
        'solved_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'student_id' => 'integer',
            'question_id' => 'integer',
            'user_id' => 'integer'
        ];
    }

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->setTimezone('Asia/Kathmandu')->format('Y-m-d H:i:s');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->setTimezone('Asia/Kathmandu')->format('Y-m-d H:i:s');
    }

    public function solver() {
        return $this->belongsTo(User::class,'user_id');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function organization()
    {
        return $this->belongsTo(User::class, 'org_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public static function boot()
    {
        parent::boot();
        static::creating(function ($doubt) {
            $doubt->status = 1;
            $doubt->student_id = Auth::guard('api')->id();
        });
    }

}
