<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentNotification extends Model
{
    //
    protected $fillable = [
        'student_profile_id',
        'title',
        'body',
        'type',
        'is_read',
    ];
}
