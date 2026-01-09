<?php

namespace App\Models;

use App\Models\Corporate\CorporateExam;
use App\Models\Corporate\CorporateExamSection;
use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    //
    protected $fillable = [
        'corporate_exam_id',
        'corporate_exam_section_id',
        'participant_id',
        'name',
        'email',
        'phone',
        'attempt_number',
        'started_at',
        'submitted_at',
        'status',
        'total_mark',
        'obtained_mark',
        'tab_switch_count'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'total_mark' => 'decimal:2',
        'obtained_mark' => 'decimal:2',
    ];

    public function exam()
    {
        return $this->belongsTo(CorporateExam::class, 'corporate_exam_id');
    }

    public function section()
    {
        return $this->belongsTo(CorporateExamSection::class, 'corporate_exam_section_id');
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class, 'exam_attempts_id');
    }
}
