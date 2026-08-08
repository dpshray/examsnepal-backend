<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function guides()
    {
        return $this->hasMany(ExamGuide::class);
    }

    public function publishedGuides()
    {
        return $this->guides()->where('status', 'published');
    }
}
