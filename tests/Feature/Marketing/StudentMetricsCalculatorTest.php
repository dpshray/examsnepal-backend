<?php

namespace Tests\Feature\Marketing;

use App\Enums\ExamTypeEnum;
use App\Services\Marketing\EventTracker;
use App\Services\Marketing\StudentMetricsCalculator;
use Illuminate\Support\Facades\DB;

class StudentMetricsCalculatorTest extends MarketingDatabaseTestCase
{
    private const FREE = ExamTypeEnum::FREE_QUIZ->value;
    private const SPRINT = ExamTypeEnum::SPRINT_QUIZ->value;
    private const MOCK = ExamTypeEnum::MOCK_TEST->value;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTimezone('Asia/Kathmandu')->setTime(12, 0));
    }

    private function refresh(int ...$ids): void
    {
        (new StudentMetricsCalculator())->refresh($ids);
    }

    public function test_student_with_no_activity_gets_empty_defaults(): void
    {
        $id = $this->student(['created_at' => now()->subDays(3)]);
        $this->refresh($id);

        $m = $this->metrics($id);
        $this->assertSame(0, (int) $m->total_attempts);
        $this->assertNull($m->first_attempt_at);
        $this->assertNull($m->days_since_last_attempt);
        $this->assertNull($m->avg_score_pct);
        $this->assertSame('insufficient_data', $m->score_trend);
        $this->assertSame('never', $m->subscription_status);
        $this->assertSame(0, (int) $m->lead_score);
        $this->assertSame(1, (int) $m->exam_type_id);
        $this->assertNotNull($m->signed_up_at);
    }

    public function test_attempt_counts_by_type_and_window(): void
    {
        $id = $this->student();
        $this->attempt($id, self::FREE, 50, now()->subDays(1));
        $this->attempt($id, self::FREE, 50, now()->subDays(10));
        $this->attempt($id, self::SPRINT, 50, now()->subDays(20));
        $this->attempt($id, self::MOCK, 50, now()->subDays(40));
        $this->attempt($id, StudentMetricsCalculator::TOPIC_QUIZ_STATUS, 50, now()->subDays(2));
        $this->attempt($id, self::FREE, 50, null);                          // legacy, untimed
        $this->attempt($id, self::MOCK, 50, now()->subHour(), completed: false); // in progress
        $this->refresh($id);

        $m = $this->metrics($id);
        $this->assertSame(6, (int) $m->total_attempts);
        $this->assertSame(3, (int) $m->free_attempts);
        $this->assertSame(1, (int) $m->sprint_attempts);
        $this->assertSame(1, (int) $m->mock_attempts);
        $this->assertSame(1, (int) $m->topic_attempts);
        $this->assertSame(2, (int) $m->attempts_last_7d);
        $this->assertSame(3, (int) $m->attempts_last_14d);
        $this->assertSame(4, (int) $m->attempts_last_30d);
        $this->assertSame(now()->subDays(40)->toDateString(), substr($m->first_attempt_at, 0, 10));
        $this->assertSame(now()->subDays(1)->toDateString(), substr($m->last_attempt_at, 0, 10));
        $this->assertSame(1, (int) $m->days_since_last_attempt);
    }

    public function test_class_exam_attempts_are_ignored(): void
    {
        $id = $this->student();
        DB::table('student_exams')->insert([
            'exam_id' => $this->exam(self::MOCK, 10, ['is_class_exam' => true]),
            'student_id' => $id, 'is_exam_completed' => 1, 'score_pct' => 90, 'submitted_at' => now(),
        ]);
        $this->refresh($id);

        $this->assertSame(0, (int) $this->metrics($id)->total_attempts);
    }

    public function test_scores_and_trend(): void
    {
        $id = $this->student();
        foreach ([40, 42, 38, 60, 65, 70] as $i => $score) {
            $this->attempt($id, self::FREE, $score, now()->subDays(12 - $i));
        }
        $this->attempt($id, self::FREE, null, now()->subDays(1)); // exam without questions: unscored
        $this->refresh($id);

        $m = $this->metrics($id);
        $this->assertEquals(52.5, (float) $m->avg_score_pct);
        $this->assertEquals(70, (float) $m->last_score_pct);
        $this->assertEquals(70, (float) $m->best_score_pct);
        $this->assertSame('improving', $m->score_trend);
    }

    public function test_streaks(): void
    {
        $id = $this->student();
        foreach ([10, 9, 8, 7, 3, 1, 0] as $daysAgo) {
            $this->attempt($id, self::FREE, 50, now()->subDays($daysAgo));
        }
        $this->attempt($id, self::FREE, 50, now()->subHours(1)); // same day twice counts once
        $this->refresh($id);

        $m = $this->metrics($id);
        $this->assertSame(2, (int) $m->current_streak_days);
        $this->assertSame(4, (int) $m->longest_streak_days);
    }

    public function test_subscription_statuses_and_revenue(): void
    {
        $active = $this->student();
        $this->payment($active);
        $this->payment($active, ['payment_status' => 'PAYMENT_ERROR', 'status' => 0, 'paid' => 1000]);

        $expiring = $this->student();
        $this->payment($expiring, ['end_date' => today()->addDays(7)]);

        $expired = $this->student();
        $this->payment($expired, ['end_date' => today()->subDay(), 'paid' => 1000]);
        $this->payment($expired, ['end_date' => today()->subDays(40), 'paid' => 1000]);

        $never = $this->student();
        $this->payment($never, ['payment_status' => 'PAYMENT_INIT', 'status' => 0]);

        $this->refresh($active, $expiring, $expired, $never);

        $this->assertSame('active', $this->metrics($active)->subscription_status);
        $this->assertEquals(2500, (float) $this->metrics($active)->total_paid_npr);
        $this->assertSame(1, (int) $this->metrics($active)->payments_count);
        $this->assertSame('expiring_soon', $this->metrics($expiring)->subscription_status);
        $this->assertSame('expired', $this->metrics($expired)->subscription_status);
        $this->assertSame(today()->subDay()->toDateString(), $this->metrics($expired)->subscription_ends_at);
        $this->assertEquals(2000, (float) $this->metrics($expired)->total_paid_npr);
        $this->assertSame('never', $this->metrics($never)->subscription_status);
        $this->assertSame(0, (int) $this->metrics($never)->payments_count);
    }

    public function test_checkout_and_pricing_signals_feed_lead_score(): void
    {
        $id = $this->student(['created_at' => now()->subDays(5)]);
        $this->payment($id, ['payment_status' => 'PAYMENT_INIT', 'status' => 0, 'subscribed_at' => now()->subHours(2)]);
        $tracker = new EventTracker();
        $tracker->track($id, EventTracker::PRICING_VIEWED, [], 'web', now()->subDays(2));
        $tracker->track($id, EventTracker::PRICING_VIEWED, [], 'web', now()->subDays(40)); // outside 30d
        $this->attempt($id, self::MOCK, 60, now()->subDays(1));
        $this->attempt($id, self::SPRINT, 60, now()->subDays(2));
        $this->refresh($id);

        $m = $this->metrics($id);
        $this->assertSame(1, (int) $m->pricing_page_views);
        $this->assertNotNull($m->checkout_started_at);
        $this->assertNull($m->checkout_completed_at);
        // 2 attempts (10) + sprint (15) + mock (20) + pricing (15) + abandoned checkout (25)
        $this->assertSame(85, (int) $m->lead_score);
        $this->assertSame(
            ['recent_attempts' => 10, 'took_sprint' => 15, 'took_mock' => 20, 'viewed_pricing_7d' => 15, 'checkout_abandoned' => 25],
            json_decode($m->lead_score_breakdown, true),
        );
    }

    public function test_paid_student_is_not_scored_for_abandoned_checkout(): void
    {
        $id = $this->student();
        $this->payment($id);
        $this->payment($id, ['payment_status' => 'PAYMENT_INIT', 'status' => 0, 'subscribed_at' => now()]);
        $this->refresh($id);

        $this->assertArrayNotHasKey('checkout_abandoned', json_decode($this->metrics($id)->lead_score_breakdown, true));
    }

    public function test_stage_and_segments_are_stored(): void
    {
        $fresh = $this->student(['created_at' => now()->subHours(2)]);
        $appUser = $this->student(['fcm_token' => 'tok', 'exam_type_id' => 9]);
        foreach ([1, 2, 3] as $d) {
            $this->attempt($appUser, self::FREE, 30, now()->subDays($d));
        }
        $mobileWeb = $this->student();
        (new EventTracker())->track($mobileWeb, EventTracker::LOGGED_IN, [], 'ios');
        $payer = $this->student();
        $this->payment($payer);
        $this->attempt($payer, self::MOCK, 80, now()->subDay());

        $this->refresh($fresh, $appUser, $mobileWeb, $payer);

        $this->assertSame('new', $this->metrics($fresh)->lifecycle_stage);
        $this->assertSame(['web_only', 'exam:1'], json_decode($this->metrics($fresh)->segments, true));

        $this->assertSame('free_only', $this->metrics($appUser)->lifecycle_stage);
        $this->assertSame(['low_performer', 'streak_active', 'app_user', 'exam:9'], json_decode($this->metrics($appUser)->segments, true));

        $this->assertContains('app_user', json_decode($this->metrics($mobileWeb)->segments, true));

        $this->assertSame('paid_active', $this->metrics($payer)->lifecycle_stage);
        $this->assertContains('high_performer', json_decode($this->metrics($payer)->segments, true));
    }

    public function test_full_refresh_writes_percentiles_per_exam_and_partial_refresh_keeps_them(): void
    {
        $low = $this->student();
        $mid = $this->student();
        $high = $this->student();
        $otherExam = $this->student(['exam_type_id' => 2]);
        $stale = $this->student();
        $this->attempt($low, self::FREE, 30, now()->subDays(1));
        $this->attempt($mid, self::FREE, 50, now()->subDays(1));
        $this->attempt($high, self::FREE, 90, now()->subDays(1));
        $this->attempt($otherExam, self::FREE, 10, now()->subDays(1));
        $this->attempt($stale, self::FREE, 99, now()->subDays(45));

        $this->assertSame(5, (new StudentMetricsCalculator())->refreshAll(chunkSize: 2));

        $this->assertEquals(0, (float) $this->metrics($low)->percentile_in_exam);
        $this->assertEquals(50, (float) $this->metrics($mid)->percentile_in_exam);
        $this->assertEquals(100, (float) $this->metrics($high)->percentile_in_exam);
        $this->assertNull($this->metrics($otherExam)->percentile_in_exam); // alone in its exam
        $this->assertNull($this->metrics($stale)->percentile_in_exam);     // nothing in 30 days

        $this->attempt($mid, self::FREE, 95, now());
        $this->refresh($mid);
        $this->assertSame(2, (int) $this->metrics($mid)->total_attempts);
        $this->assertEquals(50, (float) $this->metrics($mid)->percentile_in_exam);
    }
}
