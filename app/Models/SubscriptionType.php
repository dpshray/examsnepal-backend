<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionType extends Model
{
    protected $fillable = [
        'exam_type_id',
        'duration',
        'price',
        'status',
    ];
    public $timestamps = false;
    protected function casts()
    {
        return [
            'id' => 'integer',
            'duration' => 'integer',
            'price' => 'decimal:2',
            'status' => 'boolean',
        ];
    }
    function examType()
    {
        return $this->belongsTo(ExamType::class);
    }
}
