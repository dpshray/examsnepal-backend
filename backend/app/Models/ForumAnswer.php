<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForumAnswer extends Model {
    use HasFactory;

    protected $perPage = 12;
    // Define the fillable attributes for mass assignment
    protected $fillable = ['user_id', 'answer','forum_question_id'];

    /**
     * Define the relationship with the StudentProfile model.
     * Assumes 'user_id' is the foreign key in this table.
     */
    public function studentProfile() {
        return $this->belongsTo(StudentProfile::class, 'user_id');
    }

    /**
     * Define the relationship with the ForumQuestion model.
     * An answer belongs to a question.
     */
    public function question() {
        return $this->belongsTo(ForumQuestion::class, 'question_id');
    }
}