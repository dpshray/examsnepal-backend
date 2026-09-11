<?php

namespace App\Models\Corporate;

use App\Models\InstituteStudent;
use Illuminate\Database\Eloquent\Model;

class ClassPayment extends Model
{
    protected $fillable = [
        'class_id',
        'institute_student_id',
        'transaction_uuid',
        'amount',
        'payment_status',
        'data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'data' => 'array',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function student()
    {
        return $this->belongsTo(InstituteStudent::class, 'institute_student_id');
    }
}
