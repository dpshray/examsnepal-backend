<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForumQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'question', 'stream', 'deleted_at'];

    // Relationship with StudentProfile
    public function studentProfile()
    {
        return $this->belongsTo(StudentProfile::class, 'user_id', 'id'); // user_id references id in student_profiles
    }

    // Relationship with ForumAnswer
    public function answers()
    {
        return $this->hasMany(ForumAnswer::class, 'question_id');
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class, 'question_id');
    }
}
