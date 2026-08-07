<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherPayoutLineItem extends Model
{
    protected $fillable = [
        'teacher_payout_id',
        'exam_id',
        'unique_completions',
        'contribution_percentage',
    ];

    protected function casts(): array
    {
        return [
            'unique_completions' => 'integer',
            'contribution_percentage' => 'decimal:4',
        ];
    }

    public function payout()
    {
        return $this->belongsTo(TeacherPayout::class, 'teacher_payout_id');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
