<?php

namespace App\Services\Marketing;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The one filter definition for the marketing student list, CSV export,
 * saved custom segments and bulk tagging. Reads student_metrics joined to
 * student_profiles only.
 */
class StudentFilter
{
    /** Keys accepted from the request and stored in saved segments. */
    public const KEYS = [
        'search', 'stage', 'segment', 'exam_type_id', 'exam_type_taken',
        'attempts_min', 'attempts_max', 'score_min', 'score_max',
        'inactive_days_min', 'inactive_days_max', 'subscription_status',
        'signed_up_from', 'signed_up_to', 'signup_source', 'platform', 'tag',
        'lead_min', 'segment_id',
        // Time windows used mostly by automations.
        'signed_up_hours_min', 'signed_up_hours_max', 'last_attempt_days_min', 'last_attempt_days_max',
        'ends_in_days_min', 'ends_in_days_max', 'expired_days_min', 'expired_days_max',
        'days_to_exam_min', 'days_to_exam_max', 'pricing_views_min', 'pricing_viewed_days_max',
        // "Got automation X at least N days ago" - for follow-up emails.
        'received', 'received_days_min',
        // 0 = hasn't taken an exam yet today (Asia/Kathmandu), 1 = has.
        'attempted_today',
        // Weakest subject's average is at most N% (weak-subject emails).
        'weakest_score_max',
    ];

    public const SORTS = [
        'name' => 'p.name',
        'signed_up_at' => 'm.signed_up_at',
        'total_attempts' => 'm.total_attempts',
        'avg_score_pct' => 'm.avg_score_pct',
        'last_seen_at' => 'm.last_seen_at',
        'lead_score' => 'm.lead_score',
        'subscription_ends_at' => 'm.subscription_ends_at',
    ];

    private const TYPE_COLUMNS = [
        'free' => 'm.free_attempts',
        'sprint' => 'm.sprint_attempts',
        'mock' => 'm.mock_attempts',
        'topic' => 'm.topic_attempts',
    ];

    public static function base(): Builder
    {
        return DB::table('student_metrics as m')->join('student_profiles as p', 'p.id', '=', 'm.student_id');
    }

    /** Keep only known, non-empty filter values. */
    public static function clean(array $input): array
    {
        return collect($input)
            ->only(self::KEYS)
            ->reject(fn ($v) => $v === null || $v === '' || $v === [])
            ->all();
    }

    public static function apply(Builder $q, array $f): Builder
    {
        $f = self::clean($f);

        if (!empty($f['segment_id'])) {
            $saved = DB::table('marketing_segments')->where('id', $f['segment_id'])->value('filters');
            unset($f['segment_id']);
            // Explicit filters narrow a saved segment further.
            $f = array_merge(self::clean(json_decode($saved ?? '[]', true)), $f);
            unset($f['segment_id']);
        }

        $list = fn ($v) => array_values(array_filter((array) (is_string($v) ? explode(',', $v) : $v), fn ($x) => $x !== ''));
        $now = now();

        return $q
            ->when($f['search'] ?? null, function ($q, $term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('p.name', 'like', $like)->orWhere('p.email', 'like', $like)->orWhere('p.phone', 'like', $like));
            })
            ->when($f['stage'] ?? null, fn ($q, $v) => $q->whereIn('m.lifecycle_stage', $list($v)))
            ->when($f['segment'] ?? null, function ($q, $v) use ($list) {
                foreach ($list($v) as $segment) {
                    $q->whereJsonContains('m.segments', $segment);
                }
            })
            ->when($f['exam_type_id'] ?? null, fn ($q, $v) => $q->whereIn('m.exam_type_id', array_map('intval', $list($v))))
            ->when($f['exam_type_taken'] ?? null, function ($q, $v) use ($list) {
                $cols = array_intersect_key(self::TYPE_COLUMNS, array_flip($list($v)));
                $q->where(function ($w) use ($cols) {
                    foreach ($cols as $col) {
                        $w->orWhere($col, '>', 0);
                    }
                });
            })
            ->when(isset($f['attempts_min']), fn ($q) => $q->where('m.total_attempts', '>=', (int) $f['attempts_min']))
            ->when(isset($f['attempts_max']), fn ($q) => $q->where('m.total_attempts', '<=', (int) $f['attempts_max']))
            ->when(isset($f['score_min']), fn ($q) => $q->where('m.avg_score_pct', '>=', (float) $f['score_min']))
            ->when(isset($f['score_max']), fn ($q) => $q->where('m.avg_score_pct', '<=', (float) $f['score_max']))
            // Never-seen students count as inactive for any "at least N days" filter.
            ->when(isset($f['inactive_days_min']), fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('m.last_seen_at')
                ->orWhere('m.last_seen_at', '<=', $now->copy()->subDays((int) $f['inactive_days_min']))))
            ->when(isset($f['inactive_days_max']), fn ($q) => $q->where('m.last_seen_at', '>=', $now->copy()->subDays((int) $f['inactive_days_max'])))
            ->when($f['subscription_status'] ?? null, fn ($q, $v) => $q->whereIn('m.subscription_status', $list($v)))
            ->when($f['signed_up_from'] ?? null, fn ($q, $v) => $q->where('m.signed_up_at', '>=', $v . ' 00:00:00'))
            ->when($f['signed_up_to'] ?? null, fn ($q, $v) => $q->where('m.signed_up_at', '<=', $v . ' 23:59:59'))
            ->when($f['signup_source'] ?? null, fn ($q, $v) => $q->whereIn('p.signup_source', $list($v)))
            ->when(($f['platform'] ?? null) === 'app', fn ($q) => $q->whereJsonContains('m.segments', 'app_user'))
            ->when(($f['platform'] ?? null) === 'web', fn ($q) => $q->whereJsonContains('m.segments', 'web_only'))
            ->when($f['tag'] ?? null, fn ($q, $v) => $q->whereExists(fn ($e) => $e->from('student_tags as t')
                ->whereColumn('t.student_id', 'm.student_id')->whereIn('t.tag', $list($v))))
            ->when(isset($f['lead_min']), fn ($q) => $q->where('m.lead_score', '>=', (int) $f['lead_min']))
            // "min hours/days ago" = at or before now - N; "max" = at or after now - N.
            ->when(isset($f['signed_up_hours_min']), fn ($q) => $q->where('m.signed_up_at', '<=', $now->copy()->subHours((int) $f['signed_up_hours_min'])))
            ->when(isset($f['signed_up_hours_max']), fn ($q) => $q->where('m.signed_up_at', '>=', $now->copy()->subHours((int) $f['signed_up_hours_max'])))
            ->when(isset($f['last_attempt_days_min']), fn ($q) => $q->where('m.last_attempt_at', '<=', $now->copy()->subDays((int) $f['last_attempt_days_min'])))
            ->when(isset($f['last_attempt_days_max']), fn ($q) => $q->where('m.last_attempt_at', '>=', $now->copy()->subDays((int) $f['last_attempt_days_max'])))
            // Subscription ends in N days (ends_at is the last paid day).
            ->when(isset($f['ends_in_days_min']), fn ($q) => $q->where('m.subscription_ends_at', '>=', $now->copy()->addDays((int) $f['ends_in_days_min'])->toDateString()))
            ->when(isset($f['ends_in_days_max']), fn ($q) => $q->where('m.subscription_ends_at', '<=', $now->copy()->addDays((int) $f['ends_in_days_max'])->toDateString()))
            ->when(isset($f['expired_days_min']), fn ($q) => $q->where('m.subscription_status', 'expired')
                ->where('m.subscription_ends_at', '<=', $now->copy()->subDays((int) $f['expired_days_min'])->toDateString()))
            ->when(isset($f['expired_days_max']), fn ($q) => $q->where('m.subscription_status', 'expired')
                ->where('m.subscription_ends_at', '>=', $now->copy()->subDays((int) $f['expired_days_max'])->toDateString()))
            ->when(isset($f['days_to_exam_min']), fn ($q) => $q->where('p.target_exam_date', '>=', $now->copy()->addDays((int) $f['days_to_exam_min'])->toDateString()))
            ->when(isset($f['days_to_exam_max']), fn ($q) => $q->where('p.target_exam_date', '<=', $now->copy()->addDays((int) $f['days_to_exam_max'])->toDateString()))
            ->when(isset($f['pricing_views_min']), fn ($q) => $q->where('m.pricing_page_views', '>=', (int) $f['pricing_views_min']))
            ->when(isset($f['pricing_viewed_days_max']), fn ($q) => $q->where('m.last_pricing_viewed_at', '>=', $now->copy()->subDays((int) $f['pricing_viewed_days_max'])))
            ->when(isset($f['attempted_today']), function ($q) use ($f, $now) {
                $today = $now->copy()->setTimezone(config('marketing.timezone'))->startOfDay()->setTimezone(config('app.timezone'));
                (int) $f['attempted_today']
                    ? $q->where('m.last_attempt_at', '>=', $today)
                    : $q->where(fn ($w) => $w->whereNull('m.last_attempt_at')->orWhere('m.last_attempt_at', '<', $today));
            })
            ->when(isset($f['weakest_score_max']), fn ($q) => $q->where('m.weakest_subject_score_pct', '<=', (float) $f['weakest_score_max']))
            ->when($f['received'] ?? null, fn ($q, $key) => $q->whereExists(fn ($e) => $e->from('message_sends as rs')
                ->join('automations as ra', 'ra.id', '=', 'rs.automation_id')
                ->whereColumn('rs.student_id', 'm.student_id')
                ->where('ra.key', $key)
                ->whereNotNull('rs.sent_at')
                ->where('rs.sent_at', '<=', $now->copy()->subDays((int) ($f['received_days_min'] ?? 0)))));
    }

    public static function sort(Builder $q, ?string $sort, ?string $dir): Builder
    {
        $column = self::SORTS[$sort] ?? 'm.last_seen_at';
        $dir = strtolower((string) $dir) === 'asc' ? 'asc' : 'desc';

        // NULLs (never seen / unknown) always last.
        return $q->orderByRaw("{$column} IS NULL")->orderBy($column, $dir)->orderBy('m.student_id', 'desc');
    }
}
