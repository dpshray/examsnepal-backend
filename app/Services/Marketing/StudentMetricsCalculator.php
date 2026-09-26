<?php

namespace App\Services\Marketing;

use App\Enums\ExamTypeEnum;
use App\Enums\PaymentStatusEnum;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the denormalised student_metrics rows from student_profiles,
 * student_exams (+ cached score_pct), subscribers and events.
 *
 * An "attempt" is a completed student_exams row of a non-class exam. Its time
 * is submitted_at, falling back to updated_at/created_at; legacy rows with no
 * time at all count toward totals but not toward any time window.
 */
class StudentMetricsCalculator
{
    public const TOPIC_QUIZ_STATUS = 5; // undocumented exams.status used by topic-wise quizzes

    private const TREND_WINDOW = 3;
    private const TREND_THRESHOLD_PCT = 5;

    private CarbonImmutable $now;

    public function __construct(?CarbonInterface $now = null)
    {
        $this->now = CarbonImmutable::instance($now ?? now())->setTimezone(config('marketing.timezone'));
    }

    /** Recompute the given students. Leaves percentile_in_exam as it was (it needs the whole cohort). */
    public function refresh(array $studentIds): void
    {
        if (!$studentIds) {
            return;
        }
        $rows = $this->computeRows($studentIds);
        $this->upsert(array_map(function ($row) {
            unset($row['_avg_30d']);
            return $row;
        }, $rows));
    }

    /** Recompute every student, then percentiles. Returns the number of rows written. */
    public function refreshAll(int $chunkSize = 500): int
    {
        $count = 0;
        $avg30ByExam = [];

        DB::table('student_profiles')->select('id')->orderBy('id')
            ->chunkById($chunkSize, function (Collection $chunk) use (&$count, &$avg30ByExam) {
                $rows = $this->computeRows($chunk->pluck('id')->all());
                foreach ($rows as &$row) {
                    if ($row['_avg_30d'] !== null && $row['exam_type_id']) {
                        $avg30ByExam[$row['exam_type_id']][$row['student_id']] = $row['_avg_30d'];
                    }
                    unset($row['_avg_30d']);
                }
                $this->upsert($rows);
                $count += count($rows);
            });

        $this->writePercentiles($avg30ByExam);

        return $count;
    }

    /**
     * Percentage of same-exam peers (scored in the last 30 days) whose 30-day
     * average is strictly lower. 100 = best in the cohort. Null when the
     * student has no recent scores or has no peers.
     *
     * @param array<int, array<int, float>> $avg30ByExam exam_type_id => [student_id => avg]
     * @return array<int, float|null> student_id => percentile
     */
    public static function percentiles(array $avg30ByExam): array
    {
        $out = [];
        foreach ($avg30ByExam as $scores) {
            $n = count($scores);
            $sorted = array_values($scores);
            sort($sorted);
            foreach ($scores as $studentId => $avg) {
                if ($n < 2) {
                    $out[$studentId] = null;
                    continue;
                }
                $below = self::countBelow($sorted, $avg);
                $out[$studentId] = round($below / ($n - 1) * 100, 2);
            }
        }
        return $out;
    }

    public function computeRows(array $studentIds): array
    {
        $students = DB::table('student_profiles')
            ->whereIn('id', $studentIds)
            ->get(['id', 'exam_type_id', 'created_at', 'target_exam_date', 'last_active_at', 'last_platform', 'fcm_token', 'requested_from'])
            ->keyBy('id');

        $appEventStudents = DB::table('events')
            ->whereIn('student_id', $studentIds)
            ->whereIn('platform', [Platform::ANDROID, Platform::IOS])
            ->distinct()
            ->pluck('student_id')
            ->flip();

        $attempts = DB::table('student_exams as se')
            ->join('exams as e', 'e.id', '=', 'se.exam_id')
            ->whereIn('se.student_id', $studentIds)
            ->where('se.is_exam_completed', 1)
            ->where(fn ($q) => $q->where('e.is_class_exam', 0)->orWhereNull('e.is_class_exam'))
            ->orderBy('se.id')
            ->get([
                'se.id', 'se.student_id', 'se.score_pct', 'e.status', 'e.subject_id',
                DB::raw('COALESCE(se.submitted_at, se.updated_at, se.created_at) as attempted_at'),
            ])
            ->groupBy('student_id');

        $payments = DB::table('subscribers')
            ->whereIn('student_profile_id', $studentIds)
            ->get(['student_profile_id', 'payment_status', 'status', 'paid', 'end_date', 'subscribed_at'])
            ->groupBy('student_profile_id');

        $pricingViews = DB::table('events')
            ->whereIn('student_id', $studentIds)
            ->where('name', EventTracker::PRICING_VIEWED)
            ->where('created_at', '>=', $this->now->subDays(30))
            ->groupBy('student_id')
            ->get(['student_id', DB::raw('COUNT(*) as views'), DB::raw('MAX(created_at) as last_viewed_at')])
            ->keyBy('student_id');

        $firstPricingViews = DB::table('events')
            ->whereIn('student_id', $studentIds)
            ->where('name', EventTracker::PRICING_VIEWED)
            ->groupBy('student_id')
            ->pluck(DB::raw('MIN(created_at)'), 'student_id');

        $rows = [];
        foreach ($students as $student) {
            $rows[] = $this->computeRow(
                $student,
                $attempts->get($student->id, collect()),
                $payments->get($student->id, collect()),
                $pricingViews->get($student->id),
                $appEventStudents->has($student->id),
                $firstPricingViews[$student->id] ?? null,
            );
        }
        return $rows;
    }

    private function computeRow(object $student, Collection $attempts, Collection $payments, ?object $pricing, bool $usedAppEvents, ?string $firstPricingViewAt): array
    {
        $now = $this->now;
        $today = $now->startOfDay();

        // --- attempts -----------------------------------------------------
        $timed = $attempts->filter(fn ($a) => $a->attempted_at !== null)
            ->map(function ($a) {
                $a->at = $this->parse($a->attempted_at);
                return $a;
            })
            ->sortBy(fn ($a) => [$a->at->getTimestamp(), $a->id])
            ->values();

        $countSince = fn (int $days) => $timed->filter(fn ($a) => $a->at->gte($now->subDays($days)))->count();
        $byType = fn (int $status) => $attempts->filter(fn ($a) => (int) $a->status === $status)->count();

        $firstAt = $timed->first()?->at;
        $lastAt = $timed->last()?->at;

        // Scored attempts in time order; untimed legacy attempts go first (oldest).
        $scored = $attempts->filter(fn ($a) => $a->score_pct !== null && $a->attempted_at === null)
            ->concat($timed->filter(fn ($a) => $a->score_pct !== null))
            ->map(fn ($a) => (float) $a->score_pct)
            ->values();

        $scored30 = $timed->filter(fn ($a) => $a->score_pct !== null && $a->at->gte($now->subDays(30)))
            ->map(fn ($a) => (float) $a->score_pct);

        [$currentStreak, $longestStreak] = self::streaks(
            $timed->map(fn ($a) => $a->at->toDateString())->unique()->values()->all(),
            $today->toDateString(),
        );

        // --- payments -----------------------------------------------------
        $succeeded = $payments->filter(fn ($p) => $p->payment_status === PaymentStatusEnum::PAYMENT_SUCCESS->value && (int) $p->status === 1);
        $endsAt = $succeeded->max('end_date');
        $endsAtDate = $endsAt ? $this->parse($endsAt)->startOfDay() : null;

        $subscriptionStatus = match (true) {
            $endsAtDate === null => 'never',
            $endsAtDate->lt($today) => 'expired',
            $endsAtDate->lte($today->addDays(7)) => 'expiring_soon',
            default => 'active',
        };

        $checkoutStartedAt = ($v = $payments->max('subscribed_at')) ? $this->parse($v) : null;
        $checkoutCompletedAt = ($v = $succeeded->max('subscribed_at')) ? $this->parse($v) : null;
        $checkoutAbandoned = $checkoutStartedAt && (!$checkoutCompletedAt || $checkoutCompletedAt->lt($checkoutStartedAt));

        // --- lead score ---------------------------------------------------
        $lastPricingViewedAt = $pricing?->last_viewed_at ? $this->parse($pricing->last_viewed_at) : null;
        $signedUpAt = $student->created_at ? $this->parse($student->created_at) : null;
        $lastActiveAt = $student->last_active_at ? $this->parse($student->last_active_at) : null;
        $lastActivityAt = collect([$lastActiveAt, $lastAt])->filter()->max();

        $firstCheckoutAt = ($v = $payments->min('subscribed_at')) ? $this->parse($v) : null;
        $firstIntentAt = collect([$firstCheckoutAt, $firstPricingViewAt ? $this->parse($firstPricingViewAt) : null])->filter()->min();
        $firstPaidAt = ($v = $succeeded->min('subscribed_at')) ? $this->parse($v) : null;
        $firstValueAt = $timed->first(fn ($a) => in_array((int) $a->status, [ExamTypeEnum::SPRINT_QUIZ->value, ExamTypeEnum::MOCK_TEST->value], true))?->at;

        $sprint = $byType(ExamTypeEnum::SPRINT_QUIZ->value);
        $mock = $byType(ExamTypeEnum::MOCK_TEST->value);
        $attempts14 = $countSince(14);

        $lead = LeadScore::compute([
            'attempts_last_14d' => $attempts14,
            'sprint_attempts' => $sprint,
            'mock_attempts' => $mock,
            'last_pricing_viewed_at' => $lastPricingViewedAt,
            'checkout_abandoned' => $checkoutAbandoned && $subscriptionStatus === 'never',
            'target_exam_date' => $student->target_exam_date ? $this->parse($student->target_exam_date) : null,
            'last_activity_at' => $lastActivityAt,
            'signed_up_at' => $signedUpAt,
        ], $now);

        $row = [
            'student_id' => $student->id,
            'exam_type_id' => $student->exam_type_id,
            'signed_up_at' => $signedUpAt,

            'total_attempts' => $attempts->count(),
            'free_attempts' => $byType(ExamTypeEnum::FREE_QUIZ->value),
            'sprint_attempts' => $sprint,
            'mock_attempts' => $mock,
            'topic_attempts' => $byType(self::TOPIC_QUIZ_STATUS),
            'attempts_last_7d' => $countSince(7),
            'attempts_last_14d' => $attempts14,
            'attempts_last_30d' => $countSince(30),
            'first_attempt_at' => $firstAt,
            'last_attempt_at' => $lastAt,
            'days_since_last_attempt' => $lastAt ? (int) $lastAt->startOfDay()->diffInDays($today) : null,

            'avg_score_pct' => $scored->isEmpty() ? null : round($scored->avg(), 2),
            'last_score_pct' => $scored->last(),
            'best_score_pct' => $scored->max(),
            'score_trend' => self::trend($scored->all()),
            ...self::subjectExtremes($attempts),
            'current_streak_days' => $currentStreak,
            'longest_streak_days' => $longestStreak,

            'subscription_status' => $subscriptionStatus,
            'subscription_ends_at' => $endsAtDate?->toDateString(),
            'total_paid_npr' => round((float) $succeeded->sum('paid'), 2),
            'payments_count' => $succeeded->count(),

            'pricing_page_views' => (int) ($pricing->views ?? 0),
            'last_pricing_viewed_at' => $lastPricingViewedAt,
            'checkout_started_at' => $checkoutStartedAt,
            'checkout_completed_at' => $checkoutCompletedAt,

            'habit_reached_at' => self::habitReachedAt($timed->pluck('at')->all()),
            'first_value_at' => $firstValueAt,
            'first_intent_at' => $firstIntentAt,
            'first_paid_at' => $firstPaidAt,
            'last_seen_at' => $lastActivityAt,

            'lead_score' => $lead['score'],
            'lead_score_breakdown' => json_encode($lead['breakdown']),
            'updated_at' => $now,

            '_avg_30d' => $scored30->isEmpty() ? null : round($scored30->avg(), 2),
        ];

        $facts = [
            'signed_up_at' => $signedUpAt,
            'checkout_started_at' => $checkoutStartedAt,
            'checkout_completed_at' => $checkoutCompletedAt,
            'target_exam_date' => $student->target_exam_date ? $this->parse($student->target_exam_date) : null,
            // The web frontend never registers an FCM token, so any token means the app.
            'is_app_user' => $usedAppEvents
                || $student->fcm_token
                || in_array($student->last_platform, [Platform::ANDROID, Platform::IOS], true)
                || in_array($student->requested_from, ['ANDROID', 'IOS'], true),
        ] + $row;
        $row['lifecycle_stage'] = Lifecycle::stage($facts, $now);
        $row['segments'] = json_encode(Lifecycle::segments($facts, $now));

        return $row;
    }

    /**
     * Weakest / strongest subject from single-subject exams (exams.subject_id).
     * Needs at least two subjects with two or more scored attempts each;
     * one exam per subject is too noisy to call a weakness.
     *
     * @return array{weakest_subject_id:?int, weakest_subject_score_pct:?float, strongest_subject_id:?int}
     */
    public static function subjectExtremes(Collection $attempts, int $minAttempts = 2): array
    {
        $bySubject = $attempts
            ->filter(fn ($a) => $a->subject_id !== null && $a->score_pct !== null)
            ->groupBy('subject_id')
            ->filter(fn ($g) => $g->count() >= $minAttempts)
            ->map(fn ($g) => round($g->avg(fn ($a) => (float) $a->score_pct), 2))
            ->sort();

        if ($bySubject->count() < 2) {
            return ['weakest_subject_id' => null, 'weakest_subject_score_pct' => null, 'strongest_subject_id' => null];
        }

        return [
            'weakest_subject_id' => (int) $bySubject->keys()->first(),
            'weakest_subject_score_pct' => $bySubject->first(),
            'strongest_subject_id' => (int) $bySubject->keys()->last(),
        ];
    }

    /**
     * improving / declining when the average of the last 3 scores differs from
     * the previous 3 by at least 5 points; needs 6 scored attempts.
     *
     * @param float[] $scores oldest first
     */
    public static function trend(array $scores): string
    {
        $w = self::TREND_WINDOW;
        if (count($scores) < 2 * $w) {
            return 'insufficient_data';
        }
        $last = array_slice($scores, -$w);
        $prev = array_slice($scores, -2 * $w, $w);
        $diff = array_sum($last) / $w - array_sum($prev) / $w;

        return match (true) {
            $diff >= self::TREND_THRESHOLD_PCT => 'improving',
            $diff <= -self::TREND_THRESHOLD_PCT => 'declining',
            default => 'flat',
        };
    }

    /**
     * Time of the attempt that first completed 3 attempts within 14 days.
     *
     * @param CarbonInterface[] $times ascending
     */
    public static function habitReachedAt(array $times): ?CarbonInterface
    {
        for ($i = 2; $i < count($times); $i++) {
            if ($times[$i - 2]->diffInSeconds($times[$i]) <= 14 * 86400) {
                return $times[$i];
            }
        }
        return null;
    }

    /**
     * @param string[] $dates Y-m-d days with at least one attempt
     * @return array{0:int,1:int} [current streak, longest streak]. The current
     *   streak survives until the end of the day after the last attempt.
     */
    public static function streaks(array $dates, string $today): array
    {
        if (!$dates) {
            return [0, 0];
        }
        sort($dates);
        $longest = $run = 1;
        for ($i = 1; $i < count($dates); $i++) {
            $consecutive = Carbon::parse($dates[$i - 1])->addDay()->toDateString() === $dates[$i];
            $run = $consecutive ? $run + 1 : 1;
            $longest = max($longest, $run);
        }

        $yesterday = Carbon::parse($today)->subDay()->toDateString();
        $lastDay = end($dates);
        $current = ($lastDay === $today || $lastDay === $yesterday) ? $run : 0;

        return [$current, $longest];
    }

    private function writePercentiles(array $avg30ByExam): void
    {
        DB::table('student_metrics')->whereNotNull('percentile_in_exam')->update(['percentile_in_exam' => null]);

        $rows = [];
        foreach (self::percentiles($avg30ByExam) as $studentId => $pct) {
            if ($pct !== null) {
                $rows[] = ['student_id' => $studentId, 'percentile_in_exam' => $pct];
            }
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('student_metrics')->upsert($chunk, ['student_id'], ['percentile_in_exam']);
        }
    }

    /** Rows never carry percentile_in_exam, so an upsert leaves it untouched. */
    private function upsert(array $rows): void
    {
        if (!$rows) {
            return;
        }
        $columns = array_values(array_diff(array_keys($rows[0]), ['student_id']));
        DB::table('student_metrics')->upsert($rows, ['student_id'], $columns);
    }

    private function parse(mixed $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, config('app.timezone'))->setTimezone(config('marketing.timezone'));
    }

    private static function countBelow(array $sorted, float $value): int
    {
        $lo = 0;
        $hi = count($sorted);
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($sorted[$mid] < $value) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }
        return $lo;
    }
}
