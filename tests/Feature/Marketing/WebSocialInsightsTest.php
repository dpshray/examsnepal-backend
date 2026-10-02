<?php

namespace Tests\Feature\Marketing;

use App\Services\Marketing\Insights\InsightsService;
use App\Services\Marketing\Insights\SearchConsoleReport;
use App\Services\Marketing\Insights\TopicClassifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebSocialInsightsTest extends TestCase
{
    private CarbonImmutable $from;

    private CarbonImmutable $to;

    protected function setUp(): void
    {
        parent::setUp();
        $this->to = CarbonImmutable::parse('2026-09-28');
        $this->from = CarbonImmutable::parse('2026-09-01');

        $creds = tempnam(sys_get_temp_dir(), 'gsa');
        file_put_contents($creds, json_encode(['client_email' => 'insights@example.iam.gserviceaccount.com']));
        config([
            'marketing.insights.google_credentials' => $creds,
            'marketing.insights.ga4_property_id' => '123',
            'marketing.insights.search_console_site' => 'sc-domain:examsnepal.com',
            'marketing.insights.facebook.page_id' => '999',
            'marketing.insights.facebook.page_token' => 'token',
        ]);
        Cache::put('marketing:insights:google-token', 'fake-token', 600);
    }

    public function test_classifier_buckets_queries_by_exam_and_intent(): void
    {
        $c = new TopicClassifier();

        $this->assertSame('nursing', $c->topic('Staff Nurse loksewa model questions'));
        $this->assertSame('dental', $c->topic('dental nmcle mcq'));
        $this->assertSame('pharmacy', $c->topic('pharmacist license exam'));
        $this->assertSame('medical', $c->topic('CEE entrance mcq'));
        $this->assertNull($c->topic('success story'), '"cee" must not match inside "success"');
        $this->assertSame('past_papers', $c->intent('nmcle old questions 2081'));
        $this->assertSame('practice', $c->intent('civil engineering mcq'));
        $this->assertTrue(TopicClassifier::isBrand('ExamsNepal login'));
    }

    public function test_search_console_finds_striking_distance_low_ctr_and_under_captured_topics(): void
    {
        $q = fn ($query, $clicks, $impr, $pos) => ['keys' => [$query], 'clicks' => $clicks, 'impressions' => $impr, 'ctr' => $impr ? $clicks / $impr : 0, 'position' => $pos];
        $raw = [
            'totals' => [['clicks' => 1200, 'impressions' => 60000, 'ctr' => 0.02, 'position' => 12.3]],
            'totals_prev' => [['clicks' => 800, 'impressions' => 50000, 'ctr' => 0.016, 'position' => 14.1]],
            'series' => [],
            'queries' => [
                $q('examsnepal', 300, 600, 1.1),
                $q('nursing loksewa model questions', 40, 9000, 11.2),   // striking distance
                $q('staff nurse mcq', 30, 5000, 9.0),                   // striking distance
                $q('nmcle mcq', 20, 4000, 3.0),                        // low CTR (0.5% at #3)
                $q('dental license question', 200, 2000, 2.0),
                $q('random query', 1, 30, 40.0),
            ],
            'queries_prev' => [$q('nursing loksewa model questions', 10, 7000, 15.0), $q('dental license question', 260, 2100, 1.8)],
            'pages' => [], 'devices' => [], 'countries' => [],
        ];

        $data = (new SearchConsoleReport($this->from, $this->to))->analyze($raw, [5 => 50, 9 => 400, 1 => 550]);

        $this->assertSame(50.0, $data['kpis']['clicks']['change_pct']);
        $this->assertSame(['nursing loksewa model questions', 'staff nurse mcq'], collect($data['striking_distance'])->pluck('query')->all());
        $this->assertSame('nmcle mcq', $data['low_ctr'][0]['query']);
        $this->assertSame('nursing loksewa model questions', $data['rising'][0]['query']);
        $this->assertSame('dental license question', $data['declining'][0]['query']);

        $nursing = collect($data['topics'])->firstWhere('key', 'nursing');
        $this->assertSame(5.0, $nursing['student_share']);
        $this->assertGreaterThan(60, $nursing['search_share']);

        $titles = collect($data['insights'])->pluck('title')->implode(' | ');
        $this->assertStringContainsString('close to page 1', $titles);
        $this->assertStringContainsString('Nursing is under-captured', $titles);
    }

    public function test_sources_load_through_the_apis_and_feed_cross_insights(): void
    {
        $gaRow = fn (array $dims, array $metrics) => [
            'dimensionValues' => array_map(fn ($v) => ['value' => (string) $v], $dims),
            'metricValues' => array_map(fn ($v) => ['value' => (string) $v], $metrics),
        ];
        $ga = function ($request) use ($gaRow) {
            $body = $request->data();
            $dims = array_column($body['dimensions'], 'name');
            $metrics = array_column($body['metrics'], 'name');
            $rows = match ($dims) {
                [] => [$gaRow(['current'], [900, 800, 1500, 0.55, 95, 4000, 40]), $gaRow(['previous'], [600, 550, 1000, 0.5, 90, 3000, 20])],
                ['sessionDefaultChannelGroup'] => [
                    $gaRow(['Organic Search', 'current'], [1300, 700, 0.6, 100, 35]),
                    $gaRow(['Organic Social', 'current'], [50, 40, 0.3, 20, 1]),
                    $gaRow(['Direct', 'current'], [150, 60, 0.5, 80, 4]),
                ],
                ['sessionSourceMedium'] => [$gaRow(['google / organic'], [1300, 700, 0.6, 35]), $gaRow(['m.facebook.com / referral'], [5, 4, 0.3, 0])],
                ['dayOfWeek', 'hour'] => [$gaRow([0, 20], [150]), $gaRow([0, 21], [120]), $gaRow([3, 9], [30])],
                default => [],
            };

            return Http::response(['dimensionHeaders' => array_map(fn ($n) => ['name' => $n], $dims), 'metricHeaders' => array_map(fn ($n) => ['name' => $n], $metrics), 'rows' => $rows]);
        };
        $post = fn ($id, $date, $reactions, $comments, $media, $msg) => [
            'id' => $id, 'message' => $msg, 'created_time' => $date, 'permalink_url' => "https://fb.com/$id",
            'reactions' => ['summary' => ['total_count' => $reactions]], 'comments' => ['summary' => ['total_count' => $comments]],
            'shares' => ['count' => 2], 'attachments' => ['data' => [['media_type' => $media]]],
        ];
        Http::fake([
            'analyticsdata.googleapis.com/*' => $ga,
            'searchconsole.googleapis.com/*' => Http::response(['rows' => []]),
            'graph.facebook.com/*/999/posts*' => Http::response(['data' => [
                $post('1', '2026-09-02T14:00:00+0000', 300, 10, 'video', 'Nursing MCQ of the day'),
                $post('2', '2026-09-05T14:00:00+0000', 280, 8, 'video', 'Staff nurse loksewa notice'),
                $post('3', '2026-09-10T04:00:00+0000', 40, 1, 'photo', 'New mock test live'),
                $post('4', '2026-09-20T04:00:00+0000', 30, 1, 'photo', 'Download our app'),
            ]]),
            'graph.facebook.com/*/999/insights*' => Http::response(['error' => ['message' => '(#100) The value must be a valid insights metric']], 400),
            'graph.facebook.com/*/999*' => Http::response(['name' => 'ExamsNepal', 'followers_count' => 20000, 'fan_count' => 18000]),
        ]);

        $service = new InsightsService($this->from, $this->to);

        $analytics = $service->source('analytics');
        $this->assertNull($analytics['error']);
        $this->assertSame(50.0, $analytics['data']['kpis']['active_users']['change_pct']);
        $this->assertSame(150, $analytics['data']['heatmap'][0][20]);
        $this->assertStringContainsString('Social media sends only', collect($analytics['data']['insights'])->pluck('title')->implode('|'));

        $facebook = $service->source('facebook');
        $this->assertNull($facebook['error']);
        $this->assertSame('video', $facebook['data']['by_format'][0]['key']);
        $this->assertCount(4, $facebook['data']['unavailable_metrics']);
        $this->assertStringContainsString('Video posts get', collect($facebook['data']['insights'])->pluck('title')->implode('|'));
        // 14:00 UTC is 19:45 in Nepal.
        $this->assertSame(19, (int) substr($facebook['data']['top_posts'][0]['created_at'], 11, 2));

        $this->assertSame(['configured' => true, 'error' => null], array_intersect_key($service->source('search'), ['configured' => 1, 'error' => 1]));
    }

    public function test_api_errors_are_reported_not_cached(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Error validating access token']], 400)]);

        $result = (new InsightsService($this->from, $this->to))->source('facebook');

        $this->assertSame('Facebook: Error validating access token', $result['error']);
        $this->assertNotEmpty($result['setup']);
        $this->assertNull(Cache::get('marketing:insights:facebook:2026-09-01:2026-09-28'));
    }
}
