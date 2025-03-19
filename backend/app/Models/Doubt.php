<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Doubt extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'question_id',
        'organization_id',
        'doubt',
        'remarks',
        'status',
        'solved_by',
    ];

}
