<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBlocked extends Model
{
    //
    protected $fillable = [
        'student_id',
        'blocked_id',
    ];
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function blocker()
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}
