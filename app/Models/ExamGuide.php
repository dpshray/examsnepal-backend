<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamGuide extends Model
{
    protected $fillable = [
        'exam_category_id',
        'name',
        'slug',
        'meta_title',
        'meta_description',
        'intro',
        'conducting_body',
        'eligibility',
        'exam_pattern',
        'passing_marks',
        'application_period',
        'syllabus',
        'faqs',
        'mock_test_url',
        'question_count_label',
        'status',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'faqs' => 'array',
            'last_verified_at' => 'date',
        ];
    }

    public function category()
    {
        return $this->belongsTo(ExamCategory::class, 'exam_category_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Up to 5 other published guides in the same category, for the
     * "related exams" internal-linking block.
     */
    public function relatedGuides(int $limit = 5)
    {
        return static::published()
            ->where('exam_category_id', $this->exam_category_id)
            ->where('id', '!=', $this->id)
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
