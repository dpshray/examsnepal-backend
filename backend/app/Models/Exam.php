<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    //

    protected $fillable = [
        'organization_id',
        'exam_type_id',
        'name',
        'description',
        'exam_date',
        'exam_time',
        'is_active',
        'price',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class); // Each Exam belongs to an Organization
    }

    public function examType()
    {
        return $this->belongsTo(ExamType::class); // Each Exam belongs to an Exam Type
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }
}
