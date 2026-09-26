<?php

namespace Tests\Unit\Marketing;

use App\Console\Commands\Marketing\BackfillMarketingData;
use App\Services\Marketing\LeadScore;
use App\Services\Marketing\PaymentSource;
use App\Services\Marketing\StudentMetricsCalculator;
use App\Services\ScoreService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MarketingRulesTest extends TestCase
{
    private function lead(array $overrides): array
    {
        $now = CarbonImmutable::parse('2026-09-26 12:00');
        return LeadScore::compute($overrides + [
            'attempts_last_14d' => 0,
            'sprint_attempts' => 0,
            'mock_attempts' => 0,
            'last_pricing_viewed_at' => null,
            'checkout_abandoned' => false,
            'target_exam_date' => null,
            'last_activity_at' => $now->subDay(),
            'signed_up_at' => $now->subDays(30),
        ], $now);
    }

    public function test_lead_score_rules(): void
    {
        $now = CarbonImmutable::parse('2026-09-26 12:00');

        $this->assertSame(['score' => 0, 'breakdown' => []], $this->lead([]));
        $this->assertSame(15, $this->lead(['attempts_last_14d' => 3])['score']);
        $this->assertSame(30, $this->lead(['attempts_last_14d' => 20])['score']); // capped
        $this->assertSame(15, $this->lead(['sprint_attempts' => 4])['score']);
        $this->assertSame(20, $this->lead(['mock_attempts' => 1])['score']);
        $this->assertSame(15, $this->lead(['last_pricing_viewed_at' => $now->subDays(7)])['score']);
        $this->assertSame(0, $this->lead(['last_pricing_viewed_at' => $now->subDays(8)])['score']);
        $this->assertSame(25, $this->lead(['checkout_abandoned' => true])['score']);
        $this->assertSame(10, $this->lead(['target_exam_date' => $now->addDays(60)])['score']);
        $this->assertSame(0, $this->lead(['target_exam_date' => $now->addDays(61)])['score']);
        $this->assertSame(0, $this->lead(['target_exam_date' => $now->subDays(1)])['score']);
    }

    public function test_lead_score_inactivity_penalty_and_bounds(): void
    {
        $now = CarbonImmutable::parse('2026-09-26 12:00');

        $inactive = $this->lead(['mock_attempts' => 1, 'last_activity_at' => $now->subDays(14)]);
        $this->assertSame(['took_mock' => 20, 'inactive_14d' => -20], $inactive['breakdown']);
        $this->assertSame(0, $inactive['score']);

        // Never active: counted from signup.
        $this->assertSame(-20, $this->lead(['last_activity_at' => null, 'signed_up_at' => $now->subDays(20)])['breakdown']['inactive_14d']);
        $this->assertSame([], $this->lead(['last_activity_at' => null, 'signed_up_at' => $now->subDays(2)])['breakdown']);
        // Legacy import: no activity or signup time known.
        $this->assertSame(-20, $this->lead(['last_activity_at' => null, 'signed_up_at' => null])['breakdown']['inactive_14d']);

        $max = $this->lead([
            'attempts_last_14d' => 10, 'sprint_attempts' => 1, 'mock_attempts' => 1,
            'last_pricing_viewed_at' => $now, 'checkout_abandoned' => true, 'target_exam_date' => $now->addDays(5),
        ]);
        $this->assertSame(100, $max['score']);
        $this->assertSame(115, array_sum($max['breakdown']));
    }

    public static function trends(): array
    {
        return [
            'too few' => [[10, 20, 30, 40, 50], 'insufficient_data'],
            'improving' => [[40, 40, 40, 45, 45, 45], 'improving'],
            'declining' => [[60, 60, 60, 55, 55, 55], 'declining'],
            'flat' => [[50, 50, 50, 54, 54, 54], 'flat'],
            'uses only last six' => [[0, 0, 0, 50, 50, 50, 50, 50, 50], 'flat'],
        ];
    }

    #[DataProvider('trends')]
    public function test_score_trend(array $scores, string $expected): void
    {
        $this->assertSame($expected, StudentMetricsCalculator::trend($scores));
    }

    public function test_streaks(): void
    {
        $this->assertSame([0, 0], StudentMetricsCalculator::streaks([], '2026-09-26'));
        $this->assertSame([3, 3], StudentMetricsCalculator::streaks(['2026-09-24', '2026-09-25', '2026-09-26'], '2026-09-26'));
        $this->assertSame([2, 2], StudentMetricsCalculator::streaks(['2026-09-24', '2026-09-25'], '2026-09-26')); // alive until end of today
        $this->assertSame([0, 2], StudentMetricsCalculator::streaks(['2026-09-23', '2026-09-24'], '2026-09-26'));
        $this->assertSame([1, 3], StudentMetricsCalculator::streaks(['2026-08-30', '2026-08-31', '2026-09-01', '2026-09-26'], '2026-09-26'));
    }

    public function test_percentiles(): void
    {
        $result = StudentMetricsCalculator::percentiles([
            1 => [10 => 20.0, 11 => 50.0, 12 => 50.0, 13 => 80.0],
            2 => [20 => 70.0],
        ]);
        $this->assertEquals(0, $result[10]);
        $this->assertEquals(33.33, $result[11]); // ties share the lower rank
        $this->assertEquals(33.33, $result[12]);
        $this->assertEquals(100, $result[13]);
        $this->assertNull($result[20]);
    }

    public function test_score_percentage_matches_student_facing_marks(): void
    {
        $this->assertSame(70.0, ScoreService::percentage(7, 3, 10, 1, false, 0));
        $this->assertSame(62.5, ScoreService::percentage(7, 3, 10, 1, true, 0.25));
        $this->assertSame(35.0, ScoreService::percentage(7, 3, 20, 2, false, 0)); // 14 / 40
        $this->assertSame(0.0, ScoreService::percentage(1, 9, 10, 1, true, 1));   // floored
        $this->assertNull(ScoreService::percentage(0, 0, 0, 1, false, 0));
    }

    public function test_legacy_signup_date_parsing(): void
    {
        $this->assertSame('2026-08-06 20:42:05', BackfillMarketingData::parseLegacyDate('08/06/2026 08:42:05 pm')?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-06 20:42:05', BackfillMarketingData::parseLegacyDate('2026-08-06 20:42:05')?->format('Y-m-d H:i:s'));
        $this->assertNull(BackfillMarketingData::parseLegacyDate(''));
        $this->assertNull(BackfillMarketingData::parseLegacyDate('not a date'));
    }

    public function test_manual_admin_subscriptions_are_recognised_by_their_data(): void
    {
        // Admin form: {"remark": ...} double-encoded (json_encode into an array-cast column).
        $this->assertTrue(PaymentSource::isManual(json_encode(json_encode(['remark' => '1 month']))));
        $this->assertTrue(PaymentSource::isManual(json_encode(json_encode(['remark' => null]))));
        $this->assertTrue(PaymentSource::isManual(json_encode(['remark' => 'x'])));
        // Gateways (same TXN##### ids) store the provider payload.
        $this->assertFalse(PaymentSource::isManual(json_encode(['transaction_id' => 'TXN12345', 'ref_id' => 'R1', 'token' => 't'])));
        $this->assertFalse(PaymentSource::isManual(json_encode(['total_amount' => 1000, 'transaction_uuid' => 'TXN12345', 'product_code' => 'EPAYTEST'])));
        $this->assertFalse(PaymentSource::isManual(null));
        $this->assertFalse(PaymentSource::isManual('not json'));
    }
}
