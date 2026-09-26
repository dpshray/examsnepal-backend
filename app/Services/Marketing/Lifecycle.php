<?php

namespace App\Services\Marketing;

use Carbon\CarbonInterface;

/**
 * Lifecycle stage (exactly one per student) and segments (any number).
 * All rules live here; STAGE_PRECEDENCE is the single place that decides
 * which stage wins when several match.
 *
 * Input is one computed student_metrics row plus a few profile facts
 * (see StudentMetricsCalculator::computeRow()).
 */
class Lifecycle
{
    /**
     * First match wins. Paid states come first (they must never receive
     * upgrade messaging), then purchase intent, then activity-based states.
     * Together the rules cover every student; 'activated' is the fallback.
     */
    public const STAGE_PRECEDENCE = [
        'expiring_soon',       // subscription ends within 7 days
        'paid_active',         // active subscription, attempted in last 7 days
        'paid_inactive',       // active subscription, no attempt in 7+ days
        'expired',             // paid before, subscription over, not renewed
        'hot_lead',            // never paid; lead score >= 60 or checkout abandoned in last 30 days
        'new',                 // signed up < 24h ago, no attempts
        'registered_inactive', // signed up >= 24h ago (or unknown), no attempts
        'dormant',             // has attempts, none in 21+ days, never paid
        'engaged_free',        // took a Sprint or Mock, never paid
        'free_only',           // 3+ attempts, no Sprint/Mock, never paid
        'activated',           // 1-2 attempts
    ];

    public const HOT_LEAD_SCORE = 60;
    public const HOT_LEAD_CHECKOUT_DAYS = 30;
    public const DORMANT_DAYS = 21;
    public const PAID_INACTIVE_DAYS = 7;

    public const LOW_PERFORMER_BELOW = 40;
    public const HIGH_PERFORMER_FROM = 75;
    public const EXAM_DATE_NEAR_DAYS = 30;
    public const STREAK_ACTIVE_FROM = 2;

    /**
     * @param array $m keys: total_attempts, sprint_attempts, mock_attempts,
     *   attempts_last_7d, days_since_last_attempt, subscription_status,
     *   lead_score, checkout_started_at, checkout_completed_at, signed_up_at
     */
    public static function stage(array $m, CarbonInterface $now): string
    {
        foreach (self::STAGE_PRECEDENCE as $stage) {
            if (self::matches($stage, $m, $now)) {
                return $stage;
            }
        }
        return 'activated';
    }

    public static function matches(string $stage, array $m, CarbonInterface $now): bool
    {
        $status = $m['subscription_status'];
        $neverPaid = $status === 'never';
        $attempts = (int) $m['total_attempts'];
        $daysIdle = $m['days_since_last_attempt']; // null = never, or only untimed legacy attempts

        return match ($stage) {
            'expiring_soon' => $status === 'expiring_soon',
            'paid_active' => $status === 'active' && (int) $m['attempts_last_7d'] > 0,
            'paid_inactive' => $status === 'active',
            'expired' => $status === 'expired',
            'hot_lead' => $neverPaid && (
                (int) $m['lead_score'] >= self::HOT_LEAD_SCORE
                || self::checkoutAbandoned($m, $now, self::HOT_LEAD_CHECKOUT_DAYS)
            ),
            'new' => $attempts === 0 && $m['signed_up_at'] && $m['signed_up_at']->gt($now->copy()->subDay()),
            'registered_inactive' => $attempts === 0,
            'dormant' => $neverPaid && $attempts > 0 && ($daysIdle === null || $daysIdle >= self::DORMANT_DAYS),
            'engaged_free' => $neverPaid && ((int) $m['sprint_attempts'] > 0 || (int) $m['mock_attempts'] > 0),
            'free_only' => $neverPaid && $attempts >= 3,
            'activated' => true,
        };
    }

    /**
     * @param array $m the metrics row plus: exam_type_id, weakest_subject_id,
     *   avg_score_pct, score_trend, current_streak_days, longest_streak_days,
     *   target_exam_date (?Carbon), is_app_user (bool)
     * @return string[]
     */
    public static function segments(array $m, CarbonInterface $now): array
    {
        $s = [];
        $avg = $m['avg_score_pct'];
        $daysIdle = $m['days_since_last_attempt'];

        if ($avg !== null && $avg < self::LOW_PERFORMER_BELOW) {
            $s[] = 'low_performer';
        }
        if ($avg !== null && $avg >= self::HIGH_PERFORMER_FROM) {
            $s[] = 'high_performer';
        }
        if ($m['score_trend'] === 'declining') {
            $s[] = 'score_declining';
        }
        if ($m['score_trend'] === 'improving') {
            $s[] = 'score_improving';
        }
        if ($m['weakest_subject_id'] !== null) {
            $s[] = 'has_weak_subject';
        }
        if ($m['target_exam_date'] && $m['target_exam_date']->between($now->copy()->startOfDay(), $now->copy()->addDays(self::EXAM_DATE_NEAR_DAYS))) {
            $s[] = 'exam_date_near';
        }
        if (self::checkoutAbandoned($m, $now, self::HOT_LEAD_CHECKOUT_DAYS)) {
            $s[] = 'checkout_abandoned';
        }
        if ((int) $m['current_streak_days'] >= self::STREAK_ACTIVE_FROM) {
            $s[] = 'streak_active';
        }
        // A run of 2+ days that ended in the last week.
        if ((int) $m['current_streak_days'] === 0 && (int) $m['longest_streak_days'] >= self::STREAK_ACTIVE_FROM
            && $daysIdle !== null && $daysIdle >= 2 && $daysIdle <= 7) {
            $s[] = 'streak_broken';
        }
        $s[] = $m['is_app_user'] ? 'app_user' : 'web_only';
        if ($m['exam_type_id']) {
            $s[] = 'exam:' . $m['exam_type_id'];
        }

        return $s;
    }

    /** Latest checkout (within $days) was not followed by a completed payment. */
    public static function checkoutAbandoned(array $m, CarbonInterface $now, int $days): bool
    {
        $started = $m['checkout_started_at'];
        $completed = $m['checkout_completed_at'];

        return $started
            && $started->gte($now->copy()->subDays($days))
            && (!$completed || $completed->lt($started));
    }
}
