<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherPayout extends Model
{
    protected $fillable = [
        'user_id',
        'exam_type_id',
        'period_start',
        'period_end',
        'revenue_amount',
        'pool_percentage_used',
        'pool_amount',
        'total_unique_completions',
        'teacher_unique_completions',
        'share_percentage',
        'payout_amount',
        'status',
        'remark',
        'approved_by',
        'approved_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'revenue_amount' => 'decimal:2',
            'pool_percentage_used' => 'decimal:2',
            'pool_amount' => 'decimal:2',
            'total_unique_completions' => 'integer',
            'teacher_unique_completions' => 'integer',
            'share_percentage' => 'decimal:4',
            'payout_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function examType()
    {
        return $this->belongsTo(ExamType::class);
    }

    public function lineItems()
    {
        return $this->hasMany(TeacherPayoutLineItem::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
