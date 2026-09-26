<?php

namespace Tests\Unit\Marketing;

use App\Services\Marketing\Lifecycle;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LifecycleTest extends TestCase
{
    private static function now(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-09-26 12:00', 'Asia/Kathmandu');
    }

    /** A never-paid student who signed up 30 days ago and did nothing. */
    private static function row(array $overrides = []): array
    {
        return $overrides + [
            'total_attempts' => 0,
            'sprint_attempts' => 0,
            'mock_attempts' => 0,
            'attempts_last_7d' => 0,
            'days_since_last_attempt' => null,
            'subscription_status' => 'never',
            'lead_score' => 0,
            'checkout_started_at' => null,
            'checkout_completed_at' => null,
            'signed_up_at' => self::now()->subDays(30),
            'exam_type_id' => 1,
            'weakest_subject_id' => null,
            'avg_score_pct' => null,
            'score_trend' => 'insufficient_data',
            'current_streak_days' => 0,
            'longest_streak_days' => 0,
            'target_exam_date' => null,
            'is_app_user' => false,
        ];
    }

    private static function freeUser(int $attempts, int $daysIdle = 1): array
    {
        return ['total_attempts' => $attempts, 'days_since_last_attempt' => $daysIdle, 'attempts_last_7d' => $daysIdle < 7 ? 1 : 0];
    }

    public static function stages(): array
    {
        $now = self::now();
        return [
            'new: signed up 2h ago' => [['signed_up_at' => $now->subHours(2)], 'new'],
            'registered_inactive: 2 days, no attempts' => [['signed_up_at' => $now->subDays(2)], 'registered_inactive'],
            'registered_inactive: unknown signup (legacy)' => [['signed_up_at' => null], 'registered_inactive'],
            'activated: 1 attempt' => [self::freeUser(1), 'activated'],
            'activated: 2 attempts' => [self::freeUser(2), 'activated'],
            'free_only: 3 free attempts' => [self::freeUser(3), 'free_only'],
            'engaged_free: took a sprint' => [self::freeUser(2) + ['sprint_attempts' => 1], 'engaged_free'],
            'engaged_free: took a mock' => [self::freeUser(5) + ['mock_attempts' => 1], 'engaged_free'],
            'hot_lead: lead score 60' => [self::freeUser(5) + ['mock_attempts' => 1, 'lead_score' => 60], 'hot_lead'],
            'hot_lead: checkout abandoned' => [self::freeUser(1) + ['checkout_started_at' => $now->subHours(3)], 'hot_lead'],
            'paid_active' => [self::freeUser(5) + ['subscription_status' => 'active'], 'paid_active'],
            'paid_inactive' => [self::freeUser(5, 8) + ['subscription_status' => 'active'], 'paid_inactive'],
            'paid_inactive: paid, never attempted' => [['subscription_status' => 'active'], 'paid_inactive'],
            'expiring_soon' => [self::freeUser(5) + ['subscription_status' => 'expiring_soon'], 'expiring_soon'],
            'expired' => [self::freeUser(5, 30) + ['subscription_status' => 'expired'], 'expired'],
            'dormant: 21 days idle' => [self::freeUser(4, 21), 'dormant'],
            'dormant: only untimed legacy attempts' => [['total_attempts' => 3, 'days_since_last_attempt' => null], 'dormant'],
            'not dormant at 20 days' => [self::freeUser(4, 20), 'free_only'],
        ];
    }

    #[DataProvider('stages')]
    public function test_each_stage_rule(array $overrides, string $expected): void
    {
        $this->assertSame($expected, Lifecycle::stage(self::row($overrides), self::now()));
    }

    public static function precedence(): array
    {
        $now = self::now();
        return [
            // Paid states beat everything, so upgrade mail can never reach a payer.
            'expiring_soon beats hot_lead signals' => [['subscription_status' => 'expiring_soon', 'lead_score' => 90, 'checkout_started_at' => $now], 'expiring_soon'],
            'paid_active beats checkout abandoned' => [self::freeUser(3) + ['subscription_status' => 'active', 'checkout_started_at' => $now], 'paid_active'],
            'expired beats dormant' => [self::freeUser(3, 60) + ['subscription_status' => 'expired'], 'expired'],
            'expired is never a hot_lead' => [self::freeUser(3) + ['subscription_status' => 'expired', 'lead_score' => 80], 'expired'],
            // Fresh purchase intent beats inactivity states.
            'hot_lead beats dormant' => [self::freeUser(3, 40) + ['checkout_started_at' => $now->subDays(2)], 'hot_lead'],
            'hot_lead beats registered_inactive' => [['checkout_started_at' => $now->subDay()], 'hot_lead'],
            'hot_lead beats new' => [['signed_up_at' => $now->subHour(), 'checkout_started_at' => $now], 'hot_lead'],
            'old abandoned checkout is not hot' => [self::freeUser(3, 40) + ['checkout_started_at' => $now->subDays(31)], 'dormant'],
            'completed checkout is not hot' => [self::freeUser(3) + ['checkout_started_at' => $now->subDay(), 'checkout_completed_at' => $now->subDay()], 'free_only'],
            // Inactivity beats past engagement.
            'dormant beats engaged_free' => [self::freeUser(3, 25) + ['mock_attempts' => 1], 'dormant'],
            'dormant beats activated' => [self::freeUser(1, 25), 'dormant'],
            'engaged_free beats free_only' => [self::freeUser(6) + ['sprint_attempts' => 1], 'engaged_free'],
        ];
    }

    #[DataProvider('precedence')]
    public function test_precedence(array $overrides, string $expected): void
    {
        $this->assertSame($expected, Lifecycle::stage(self::row($overrides), self::now()));
    }

    public function test_precedence_list_is_the_single_source_of_stages(): void
    {
        $stages = Lifecycle::STAGE_PRECEDENCE;
        $this->assertCount(11, array_unique($stages));
        $this->assertSame('activated', end($stages)); // catch-all comes last
    }

    private function segments(array $overrides): array
    {
        return Lifecycle::segments(self::row($overrides), self::now());
    }

    public function test_baseline_segments(): void
    {
        $this->assertSame(['web_only', 'exam:1'], $this->segments([]));
        $this->assertSame(['app_user'], $this->segments(['is_app_user' => true, 'exam_type_id' => null]));
    }

    public function test_performance_segments(): void
    {
        $this->assertContains('low_performer', $this->segments(['avg_score_pct' => 39.99]));
        $this->assertNotContains('low_performer', $this->segments(['avg_score_pct' => 40]));
        $this->assertContains('high_performer', $this->segments(['avg_score_pct' => 75]));
        $this->assertNotContains('high_performer', $this->segments(['avg_score_pct' => 74.9]));
        $this->assertNotContains('low_performer', $this->segments(['avg_score_pct' => null]));
        $this->assertContains('score_declining', $this->segments(['score_trend' => 'declining']));
        $this->assertContains('score_improving', $this->segments(['score_trend' => 'improving']));
        $this->assertContains('has_weak_subject', $this->segments(['weakest_subject_id' => 7]));
    }

    public function test_intent_and_date_segments(): void
    {
        $now = self::now();
        $this->assertContains('exam_date_near', $this->segments(['target_exam_date' => $now->addDays(30)]));
        $this->assertNotContains('exam_date_near', $this->segments(['target_exam_date' => $now->addDays(31)]));
        $this->assertNotContains('exam_date_near', $this->segments(['target_exam_date' => $now->subDay()]));
        $this->assertContains('checkout_abandoned', $this->segments(['checkout_started_at' => $now->subHour()]));
        $this->assertNotContains('checkout_abandoned', $this->segments(['checkout_started_at' => $now->subHour(), 'checkout_completed_at' => $now]));
    }

    public function test_streak_segments(): void
    {
        $this->assertContains('streak_active', $this->segments(['current_streak_days' => 2, 'longest_streak_days' => 2]));
        $this->assertNotContains('streak_active', $this->segments(['current_streak_days' => 1, 'longest_streak_days' => 1]));
        $broken = ['current_streak_days' => 0, 'longest_streak_days' => 4, 'days_since_last_attempt' => 3];
        $this->assertContains('streak_broken', $this->segments($broken));
        $this->assertNotContains('streak_broken', $this->segments(['days_since_last_attempt' => 10] + $broken));
        $this->assertNotContains('streak_broken', $this->segments(['longest_streak_days' => 1] + $broken));
    }
}
