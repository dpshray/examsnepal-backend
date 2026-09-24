<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NoticeSubscription extends Model
{
    protected $fillable = [
        'student_profile_id', 'email', 'categories', 'exam_tags', 'channel', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'exam_tags' => 'array',
            'is_active' => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscription) {
            $subscription->unsubscribe_token ??= Str::random(48);
        });
    }

    public function student()
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    /** Whether a published notice matches this subscription's filters. */
    public function matches(Notice $notice): bool
    {
        $categories = $this->categories ?? [];
        $tags = $this->exam_tags ?? [];

        $categoryOk = empty($categories) || in_array($notice->category, $categories, true);
        $tagOk = empty($tags) || count(array_intersect($tags, $notice->exam_tags ?? [])) > 0;

        return $categoryOk && $tagOk;
    }
}
