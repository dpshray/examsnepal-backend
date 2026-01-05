<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentNotificationRead extends Model
{
    protected $fillable = [
        'student_profile_id',
        'student_notification_id',
        'read_at'
    ];

    function studentNotification() {
        return $this->belongsTo(StudentNotification::class);
    }
}
