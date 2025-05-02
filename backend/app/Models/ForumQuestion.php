<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ForumQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'question', 'deleted_at'];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($forum_question) {
            $user = Auth::guard('api')->user();
            $forum_question->user_id = $user->id;
            $forum_question->deleted = 0;
        });
    }

    // Relationship with StudentProfile
    public function studentProfile()
    {
        return $this->belongsTo(StudentProfile::class, 'user_id', 'id'); // user_id references id in student_profiles
    }

    // Relationship with ForumAnswer
    public function answers()
    {
        return $this->hasMany(ForumAnswer::class, 'forum_question_id');
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class, 'question_id');
    }
}
