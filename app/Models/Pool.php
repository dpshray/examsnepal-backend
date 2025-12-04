<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pool extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'question_id',
        'option_id',
        'is_correct'
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'student_pool_id' => 'integer',
            'question_id' => 'integer',
            'option_id' => 'integer',
        ];
    }
}
