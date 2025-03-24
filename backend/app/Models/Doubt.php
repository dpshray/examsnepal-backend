<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Doubt extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'email',
        'exam_id',
        'student_id',
        'question_id',
        'org_id',
        'doubt',
        'date',
        'remarks',
        'status',
        'solved_by',
    ];

}
