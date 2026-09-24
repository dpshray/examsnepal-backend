<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Notice extends Model
{
    public const CATEGORIES = ['loksewa', 'entrance', 'license'];

    public const TYPES = [
        'vacancy', 'exam_schedule', 'result', 'admit_card', 'syllabus',
        'interview', 'entrance_form', 'license_exam', 'other',
    ];

    public const STATUSES = ['pending', 'published', 'rejected', 'archived'];

    protected $fillable = [
        'source_id', 'category', 'sub_category', 'organization', 'province',
        'title_original', 'title_en', 'title_ne', 'slug', 'summary_en', 'summary_ne',
        'source_url', 'attachment_urls',
        'published_date_bs', 'published_date_ad',
        'application_start_ad', 'application_deadline_ad', 'double_fee_deadline_ad',
        'exam_date_ad', 'exam_date_bs',
        'notice_type', 'posts', 'fees', 'eligibility', 'exam_centers', 'exam_tags',
        'content_hash', 'ai_confidence', 'ai_model', 'ai_raw', 'ai_input_tokens', 'ai_output_tokens',
        'enriched_at', 'enrichment_error',
        'status', 'is_featured', 'published_at',
    ];

    protected $hidden = ['ai_raw'];

    protected function casts(): array
    {
        return [
            'attachment_urls' => 'array',
            'posts' => 'array',
            'fees' => 'array',
            'eligibility' => 'array',
            'exam_centers' => 'array',
            'exam_tags' => 'array',
            'ai_raw' => 'array',
            'ai_confidence' => 'float',
            'is_featured' => 'boolean',
            'published_date_ad' => 'date',
            'application_start_ad' => 'date',
            'application_deadline_ad' => 'date',
            'double_fee_deadline_ad' => 'date',
            'exam_date_ad' => 'date',
            'enriched_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $notice) {
            if (! $notice->slug) {
                $notice->slug = static::uniqueSlug($notice->slugBase());
            }
        });

        static::saving(function (self $notice) {
            if ($notice->isDirty('status') && $notice->status === 'published' && ! $notice->published_at) {
                $notice->published_at = now();
            }
        });
    }

    public function source()
    {
        return $this->belongsTo(NoticeSource::class, 'source_id');
    }

    public function reports()
    {
        return $this->hasMany(NoticeReport::class);
    }

    // ---------------------------------------------------------------- scopes

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** Published + archived: archived notices stay reachable by URL. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'archived']);
    }

    public function scopeClosingWithin(Builder $query, int $days): Builder
    {
        return $query->whereNotNull('application_deadline_ad')
            ->whereBetween('application_deadline_ad', [today(), today()->addDays($days)]);
    }

    public function scopeExamWithin(Builder $query, int $days): Builder
    {
        return $query->whereNotNull('exam_date_ad')
            ->whereBetween('exam_date_ad', [today(), today()->addDays($days)]);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('published_date_ad')->orderByDesc('id');
    }

    // --------------------------------------------------------------- helpers

    public function displayTitle(): string
    {
        return $this->title_en ?: ($this->title_ne ?: $this->title_original);
    }

    /**
     * Rebuild the slug from the English title - only while the notice has
     * never been published, so public URLs never change once live.
     */
    public function refreshSlugFromEnglishTitle(): void
    {
        if ($this->published_at || ! $this->title_en) {
            return;
        }

        $base = static::trimSlug(Str::slug($this->title_en));
        if ($base && ! Str::startsWith($this->slug, $base)) {
            $this->slug = static::uniqueSlug($base, $this->id);
        }
    }

    public function slugBase(): string
    {
        $title = $this->title_en ?: $this->title_original;
        $base = static::trimSlug(Str::slug((string) $title));

        // Str::slug() transliterates Devanagari into unreadable ASCII; fall
        // back to organization + date until the English title arrives.
        if (! $this->title_en && preg_match('/\p{Devanagari}/u', (string) $this->title_original)) {
            $base = Str::slug(Str::limit($this->organization, 60, '').' notice '.optional($this->published_date_ad)->format('Y-m-d'));
        }

        return $base ?: 'notice';
    }

    /** Cap a slug at ~80 chars on a word boundary. */
    public static function trimSlug(string $slug, int $max = 80): string
    {
        if (strlen($slug) <= $max) {
            return $slug;
        }
        $cut = substr($slug, 0, $max + 1);

        return rtrim(substr($cut, 0, strrpos($cut, '-') ?: $max), '-');
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $base = trim(Str::limit($base, 170, ''), '-') ?: 'notice';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
