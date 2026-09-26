<?php

namespace Tests\Feature\Marketing;

use App\Enums\ExamTypeEnum;
use App\Services\Marketing\EventTracker;
use App\Services\Marketing\OverviewReport;
use App\Services\Marketing\StudentMetricsCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MarketingDashboardTest extends MarketingDatabaseTestCase
{
    private const FREE = ExamTypeEnum::FREE_QUIZ->value;
    private const MOCK = ExamTypeEnum::MOCK_TEST->value;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00', 'Asia/Kathmandu'));
        $this->withoutMiddleware();
    }

    private function refreshAll(): void
    {
        (new StudentMetricsCalculator())->refreshAll();
    }

    private function report(?int $examTypeId = null): OverviewReport
    {
        return new OverviewReport(CarbonImmutable::today()->subDays(29), CarbonImmutable::today(), $examTypeId);
    }

    /** Students at every funnel step, all signed up 10 days ago. */
    private function funnelCohort(): array
    {
        $signup = ['created_at' => now()->subDays(10)];
        $threeAttempts = function (int $id, bool $withMock) {
            $this->attempt($id, self::FREE, 50, now()->subDays(9));
            $this->attempt($id, self::FREE, 50, now()->subDays(8));
            $this->attempt($id, $withMock ? self::MOCK : self::FREE, 50, now()->subDays(7));
        };

        $s = [];
        $s['registered'] = $this->student($signup);
        $s['first_exam'] = $this->student($signup);
        $this->attempt($s['first_exam'], self::FREE, 50, now()->subDays(9));
        $s['habit'] = $this->student($signup);
        $threeAttempts($s['habit'], false);
        $s['value'] = $this->student($signup);
        $threeAttempts($s['value'], true);
        $s['intent'] = $this->student($signup);
        $threeAttempts($s['intent'], true);
        (new EventTracker())->track($s['intent'], EventTracker::PRICING_VIEWED, [], 'web', now()->subDays(6));
        $s['paid'] = $this->student($signup);
        $threeAttempts($s['paid'], true);
        $this->payment($s['paid'], ['subscribed_at' => now()->subDays(6)]);
        $s['renewed'] = $this->student($signup);
        $threeAttempts($s['renewed'], true);
        $this->payment($s['renewed'], ['subscribed_at' => now()->subDays(6)]);
        $this->payment($s['renewed'], ['subscribed_at' => now()->subDays(2)]);
        // Paid without ever taking an exam: counts as registered only in a strict funnel.
        $s['paid_no_exam'] = $this->student($signup);
        $this->payment($s['paid_no_exam'], ['subscribed_at' => now()->subDays(6)]);
        // Signed up in the previous 30-day period.
        $s['previous'] = $this->student(['created_at' => now()->subDays(45)]);

        $this->refreshAll();
        return $s;
    }

    public function test_funnel_is_strict_over_the_signup_cohort(): void
    {
        $this->funnelCohort();

        $funnel = collect($this->report()->funnel())->keyBy('key');

        $this->assertSame(
            ['registered' => 8, 'first_exam' => 6, 'habit' => 5, 'value' => 4, 'intent' => 3, 'paid' => 2, 'renewed' => 1],
            $funnel->map->count->all(),
        );
        $this->assertNull($funnel['registered']['drop_off_pct']);
        $this->assertEquals(25.0, $funnel['first_exam']['drop_off_pct']);
        $this->assertEquals(50.0, $funnel['renewed']['conversion_from_previous']);
    }

    public function test_kpis_and_change_vs_previous_period(): void
    {
        $s = $this->funnelCohort();
        $this->manualPayment($s['registered'], ['subscribed_at' => now()->subDay()]);
        $this->refreshAll();

        $kpis = $this->report()->build()['kpis'];

        $this->assertSame(8, $kpis['new_registrations']['value']);
        $this->assertSame(1, $kpis['new_registrations']['previous']);
        $this->assertEquals(700.0, $kpis['new_registrations']['change_pct']);
        $this->assertEquals(75.0, $kpis['activation_rate']['value']);           // 6 of 8 within 7 days
        $this->assertSame(0, $kpis['active_students_7d']['value']);            // last attempts were 7+ days ago
        $this->assertSame(6, $kpis['active_students_30d']['value']);
        $this->assertSame(12, $kpis['attempts_free']['value']);
        $this->assertSame(4, $kpis['attempts_mock']['value']);
        // First-time payers (paid, renewed, paid_no_exam, manual) ÷ unpaid attempters (6).
        $this->assertEquals(66.7, $kpis['free_to_paid_rate']['value']);
        // Admin-added (offline) sales count, and are also broken out.
        $this->assertSame(5, $kpis['new_paid_subscriptions']['value']);
        $this->assertEquals(12500, $kpis['revenue_npr']['value']);
        $this->assertSame([['months' => 3, 'count' => 5, 'revenue_npr' => 12500.0]], $kpis['revenue_by_plan']['value']);
        $this->assertSame(1, $kpis['manual_subscriptions']['value']);
        $this->assertEquals(2500, $kpis['manual_revenue_npr']['value']);
        $this->assertSame(0, $kpis['emails_sent']['value']);
    }

    public function test_renewal_rate(): void
    {
        $lapsed = $this->student();
        $this->payment($lapsed, ['subscribed_at' => now()->subDays(100), 'end_date' => today()->subDays(5)]);
        $renewer = $this->student();
        $this->payment($renewer, ['subscribed_at' => now()->subDays(100), 'end_date' => today()->subDays(3)]);
        $this->payment($renewer, ['subscribed_at' => now()->subDays(2), 'end_date' => today()->addMonths(3)]);
        $this->refreshAll();

        $this->assertEquals(50.0, $this->report()->kpis(CarbonImmutable::today()->subDays(29), CarbonImmutable::today()->endOfDay())['renewal_rate']);
    }

    public function test_exam_type_filter(): void
    {
        $this->funnelCohort();
        $this->student(['exam_type_id' => 2, 'created_at' => now()->subDays(3)]);
        $this->refreshAll();

        $this->assertSame(1, $this->report(2)->build()['kpis']['new_registrations']['value']);
        $this->assertSame(8, $this->report(1)->build()['kpis']['new_registrations']['value']);
        $this->assertSame(0, $this->report(2)->build()['kpis']['attempts_free']['value']);
    }

    public function test_daily_series_and_stage_distribution(): void
    {
        $this->funnelCohort();
        $report = $this->report()->build();

        $series = collect($report['daily']['series'])->keyBy('date');
        $this->assertSame('day', $report['daily']['granularity']);
        $this->assertCount(30, $series);
        $this->assertSame(8, $series[now()->subDays(10)->toDateString()]['registrations']);
        $this->assertSame(6, $series[now()->subDays(9)->toDateString()]['first_exams']);
        $this->assertSame(6, $series[now()->subDays(9)->toDateString()]['free']);
        $this->assertSame(4, $series[now()->subDays(7)->toDateString()]['mock']);
        $this->assertSame(3, $series[now()->subDays(6)->toDateString()]['payments']);

        $stages = collect($report['stage_distribution']);
        $this->assertSame(9, $stages->sum('count'));
        $this->assertSame('expiring_soon', $stages->first()['stage']);
    }

    public function test_top_exams_by_attempts_and_conversion(): void
    {
        $popular = $this->exam(self::FREE, 5, ['exam_name' => 'Popular quiz']);
        $converting = $this->exam(self::MOCK, 5, ['exam_name' => 'Converting mock']);
        foreach (range(1, 3) as $i) {
            $this->attempt($this->student(), self::FREE, 50, now()->subDays(5), examId: $popular);
        }
        $payer = $this->student();
        $this->attempt($payer, self::MOCK, 70, now()->subDays(5), examId: $converting);
        $this->payment($payer, ['subscribed_at' => now()->subDays(4)]);
        $this->attempt($this->student(), self::MOCK, 70, now()->subDays(5), examId: $converting);
        $this->refreshAll();

        $top = $this->report()->topExams(limit: 5, minStudentsForConversion: 1);

        $this->assertSame('Popular quiz', $top['by_attempts'][0]['exam_name']);
        $this->assertSame(3, $top['by_attempts'][0]['attempts']);
        $this->assertSame('Converting mock', $top['by_conversion'][0]['exam_name']);
        $this->assertEquals(50.0, $top['by_conversion'][0]['conversion_rate']);
    }

    public function test_weekly_cohorts(): void
    {
        $lastWeek = now()->startOfWeek()->subWeek();
        $active = $this->student(['created_at' => $lastWeek->copy()->addDay()]);
        $this->student(['created_at' => $lastWeek->copy()->addDays(2)]);
        $this->attempt($active, self::FREE, 50, $lastWeek->copy()->addDays(3));
        $this->attempt($active, self::FREE, 50, now()->startOfWeek()->addHour());
        $this->refreshAll();

        $rows = $this->report()->cohorts(2)['rows'];

        $this->assertSame(['week_start' => $lastWeek->toDateString(), 'size' => 2, 'cells' => [50.0, 50.0]], $rows[0]);
        $this->assertSame(['week_start' => now()->startOfWeek()->toDateString(), 'size' => 0, 'cells' => [null]], $rows[1]);
    }

    public function test_habit_needs_three_attempts_within_fourteen_days(): void
    {
        $t = fn ($d) => CarbonImmutable::parse('2026-09-01')->addDays($d);
        $this->assertNull(StudentMetricsCalculator::habitReachedAt([$t(0), $t(10), $t(20)]));
        $this->assertEquals($t(24), StudentMetricsCalculator::habitReachedAt([$t(0), $t(10), $t(20), $t(24)]));
        $this->assertEquals($t(14), StudentMetricsCalculator::habitReachedAt([$t(0), $t(1), $t(14)]));
    }

    // ------------------------------------------------------------ API

    private function ids(string $query): array
    {
        return collect($this->getJson("/api/admin/marketing/students?{$query}")->assertOk()->json('data.data'))
            ->pluck('student_id')->sort()->values()->all();
    }

    public function test_student_list_filters(): void
    {
        $s = $this->funnelCohort();
        $nurse = $this->student(['exam_type_id' => 2, 'name' => 'Sita Nurse', 'created_at' => now()->subDays(3), 'fcm_token' => 'x']);
        $this->refreshAll();
        DB::table('student_tags')->insert(['student_id' => $s['intent'], 'tag' => 'vip']);

        $this->assertSame([$nurse], $this->ids('search=Sita'));
        $this->assertSame([$nurse], $this->ids('exam_type_id=2'));
        $this->assertSame([$nurse], $this->ids('platform=app'));
        $this->assertSame([$s['intent']], $this->ids('tag=vip'));
        $this->assertSame([$s['paid'], $s['renewed'], $s['paid_no_exam']], $this->ids('subscription_status=active'));
        $this->assertSame([$s['value'], $s['intent'], $s['paid'], $s['renewed']], $this->ids('exam_type_taken=mock'));
        $this->assertSame([$s['first_exam']], $this->ids('attempts_min=1&attempts_max=1'));
        $this->assertSame([$s['previous']], $this->ids('signed_up_to=' . now()->subDays(40)->toDateString()));
        $this->assertSame([$nurse], $this->ids('signed_up_from=' . now()->subDays(5)->toDateString()));
        $this->assertSame([$s['registered'], $s['previous'], $nurse], $this->ids('stage=registered_inactive'));
        $this->assertSame([$s['registered'], $s['paid_no_exam'], $s['previous']], $this->ids('segment=web_only,exam:1&attempts_max=0'));
        // Never-seen students count as inactive for a minimum-days filter.
        $this->assertContains($s['previous'], $this->ids('inactive_days_min=30'));
        $this->assertNotContains($s['habit'], $this->ids('inactive_days_min=30'));
        $this->assertSame([], $this->ids('score_min=51'));
    }

    public function test_student_list_rows_sort_and_paginate(): void
    {
        $this->funnelCohort();

        $res = $this->getJson('/api/admin/marketing/students?sort=lead_score&dir=desc&per_page=3')->assertOk();

        $this->assertSame(9, $res->json('data.total'));
        $this->assertSame(3, $res->json('data.last_page'));
        $rows = $res->json('data.data');
        $this->assertCount(3, $rows);
        $this->assertGreaterThanOrEqual($rows[1]['lead_score'], $rows[0]['lead_score']);
        $this->assertSame('Medical', $rows[0]['target_exam']);
        $this->assertIsArray($rows[0]['segments']);
        $this->assertSame([], $rows[0]['tags']);
    }

    public function test_saved_segments_and_tagging(): void
    {
        $s = $this->funnelCohort();

        $id = $this->postJson('/api/admin/marketing/segments', [
            'name' => 'Mock takers', 'filters' => ['exam_type_taken' => 'mock', 'bogus' => 'ignored'],
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/admin/marketing/segments', ['name' => 'Empty', 'filters' => ['bogus' => 1]])->assertStatus(422);

        $segment = collect($this->getJson('/api/admin/marketing/segments')->assertOk()->json('data'))->firstWhere('id', $id);
        $this->assertSame(['exam_type_taken' => 'mock'], $segment['filters']);
        $this->assertSame(4, $segment['count']);
        // Explicit filters narrow a saved segment.
        $this->assertSame([$s['paid'], $s['renewed']], $this->ids("segment_id={$id}&subscription_status=active"));

        $this->postJson('/api/admin/marketing/students/tags', ['tag' => 'mock-q3', 'action' => 'add', 'filters' => ['segment_id' => $id]])
            ->assertOk()->assertJsonPath('data.affected', 4);
        $this->postJson('/api/admin/marketing/students/tags', ['tag' => 'mock-q3', 'action' => 'add', 'student_ids' => [$s['value'], $s['habit']]])
            ->assertJsonPath('data.affected', 1); // already tagged one is ignored
        $this->assertCount(5, $this->ids('tag=mock-q3'));
        $this->postJson('/api/admin/marketing/students/tags', ['tag' => 'mock-q3', 'action' => 'remove', 'student_ids' => [$s['habit']]])
            ->assertJsonPath('data.affected', 1);
        $this->postJson('/api/admin/marketing/students/tags', ['tag' => '<script>', 'action' => 'add', 'student_ids' => [1]])->assertStatus(422);

        $this->deleteJson("/api/admin/marketing/segments/{$id}")->assertOk();
        $this->assertSame(0, DB::table('marketing_segments')->count());
    }

    public function test_student_profile_and_export(): void
    {
        $s = $this->funnelCohort();

        $res = $this->getJson("/api/admin/marketing/students/{$s['renewed']}")->assertOk();
        $this->assertSame('Medical', $res->json('data.profile.target_exam'));
        $this->assertSame('paid_active', $res->json('data.metrics.lifecycle_stage'));
        $this->assertCount(3, $res->json('data.attempts'));
        $this->assertSame('mock', $res->json('data.attempts.0.type'));
        $this->assertCount(3, $res->json('data.score_series'));
        $this->assertCount(2, $res->json('data.payments'));
        $this->assertIsArray($res->json('data.metrics.lead_score_breakdown'));
        $this->getJson('/api/admin/marketing/students/999999')->assertNotFound();

        $csv = $this->get('/api/admin/marketing/students/export?exam_type_taken=mock')->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", $csv)));
        $this->assertStringStartsWith('student_id,name,email', $lines[0]);
        $this->assertCount(5, $lines); // header + 4 mock takers
    }

    public function test_overview_and_meta_endpoints(): void
    {
        $this->funnelCohort();

        $this->getJson('/api/admin/marketing/overview?from=' . now()->subDays(29)->toDateString() . '&to=' . now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.kpis.new_registrations.value', 8)
            ->assertJsonCount(7, 'data.funnel');
        $this->getJson('/api/admin/marketing/overview?from=2026-09-10&to=2026-09-01')->assertStatus(422);
        $this->getJson('/api/admin/marketing/cohorts?weeks=4')->assertOk()->assertJsonCount(4, 'data.rows');
        $this->getJson('/api/admin/marketing/meta')->assertOk()
            ->assertJsonPath('data.stages.0', 'expiring_soon')
            ->assertJsonFragment(['exam:9']);
    }
}
