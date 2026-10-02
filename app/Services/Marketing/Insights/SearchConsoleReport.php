<?php

namespace App\Services\Marketing\Insights;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Google Search Console: what people search for before they find us, where we
 * rank, and which queries are closest to paying off with a little work.
 */
class SearchConsoleReport
{
    private const QUERY_LIMIT = 1000;

    /** Rough organic CTR by position (industry averages, desktop+mobile). */
    private const EXPECTED_CTR = [1 => 28.0, 2 => 15.0, 3 => 10.0, 4 => 7.0, 5 => 5.5, 6 => 4.5, 7 => 3.5, 8 => 3.0, 9 => 2.5, 10 => 2.2];

    public function __construct(private CarbonImmutable $from, private CarbonImmutable $to) {}

    public static function configured(): bool
    {
        return GoogleApi::hasCredentials() && (bool) config('marketing.insights.search_console_site');
    }

    public function build(): array
    {
        [$prevFrom, $prevTo] = Insight::previousPeriod($this->from, $this->to);
        $site = config('marketing.insights.search_console_site');
        $url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'.rawurlencode($site).'/searchAnalytics/query';
        $q = fn (CarbonImmutable $from, CarbonImmutable $to, array $dims, int $limit = 1000) => [$url, [
            'startDate' => $from->toDateString(),
            'endDate' => $to->toDateString(),
            'dimensions' => $dims,
            'rowLimit' => $limit,
            'dataState' => 'all',
        ]];

        $raw = (new GoogleApi())->postMany([
            'totals' => $q($this->from, $this->to, []),
            'totals_prev' => $q($prevFrom, $prevTo, []),
            'series' => $q($this->from, $this->to, ['date']),
            'queries' => $q($this->from, $this->to, ['query'], self::QUERY_LIMIT),
            'queries_prev' => $q($prevFrom, $prevTo, ['query'], self::QUERY_LIMIT),
            'pages' => $q($this->from, $this->to, ['page'], 50),
            'devices' => $q($this->from, $this->to, ['device']),
            'countries' => $q($this->from, $this->to, ['country'], 10),
        ]);

        return $this->analyze(array_map(fn ($r) => $r['rows'] ?? [], $raw), $this->studentsByExamType());
    }

    /**
     * Pure analysis over Search Console rows (testable without Google).
     *
     * @param  array<string, array>  $raw  rows per request name (see build())
     * @param  array<int, int>  $studentsByExamType  exam_type_id => students
     */
    public function analyze(array $raw, array $studentsByExamType = []): array
    {
        $classifier = new TopicClassifier();
        $totals = $raw['totals'][0] ?? ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => null];
        $prev = $raw['totals_prev'][0] ?? null;

        $queries = $this->queryRows($raw['queries'] ?? [], $classifier);
        $prevQueries = collect($this->queryRows($raw['queries_prev'] ?? [], $classifier))->keyBy('query');
        foreach ($queries as &$row) {
            $before = $prevQueries->get($row['query']);
            $row['previous_clicks'] = $before['clicks'] ?? 0;
            $row['previous_position'] = $before['position'] ?? null;
            $row['click_change'] = $row['clicks'] - $row['previous_clicks'];
        }
        unset($row);
        $queries = collect($queries);

        $kpis = [
            'clicks' => Insight::kpi($totals['clicks'] ?? 0, $prev['clicks'] ?? null),
            'impressions' => Insight::kpi($totals['impressions'] ?? 0, $prev['impressions'] ?? null),
            'ctr' => Insight::kpi(round(($totals['ctr'] ?? 0) * 100, 2), $prev ? round($prev['ctr'] * 100, 2) : null),
            'position' => Insight::kpi(isset($totals['position']) ? round($totals['position'], 1) : null, $prev ? round($prev['position'], 1) : null),
        ];

        // Big enough to matter: at least 0.5% of all query impressions (min 20).
        $minImpressions = max(20, (int) ($queries->sum('impressions') * 0.005));

        // Opportunity lists skip brand searches: those people find us anyway.
        $generic = $queries->reject(fn ($r) => $r['brand']);
        $striking = $generic
            ->filter(fn ($r) => $r['position'] >= 8 && $r['position'] <= 20 && $r['impressions'] >= $minImpressions)
            ->map(fn ($r) => $r + ['potential_clicks' => max(0, (int) round($r['impressions'] * self::EXPECTED_CTR[3] / 100 - $r['clicks']))])
            ->sortByDesc('potential_clicks')->take(15)->values();

        $lowCtr = $generic
            ->filter(fn ($r) => $r['position'] <= 7 && $r['impressions'] >= $minImpressions
                && $r['ctr'] < $this->expectedCtr($r['position']) * 0.5)
            ->map(fn ($r) => $r + [
                'expected_ctr' => $this->expectedCtr($r['position']),
                'potential_clicks' => max(0, (int) round($r['impressions'] * $this->expectedCtr($r['position']) / 100 - $r['clicks'])),
            ])
            ->sortByDesc('potential_clicks')->take(15)->values();

        $rising = $generic->filter(fn ($r) => $r['click_change'] > 0)->sortByDesc('click_change')->take(10)->values();
        $newQueries = $generic->filter(fn ($r) => $r['previous_clicks'] === 0 && ! $prevQueries->has($r['query']) && $r['impressions'] >= $minImpressions)
            ->sortByDesc('impressions')->take(10)->values();
        $currentKeys = $queries->keyBy('query');
        $declining = $prevQueries->values()
            ->map(fn ($p) => [
                'query' => $p['query'], 'topic' => $p['topic'],
                'clicks' => $currentKeys->get($p['query'])['clicks'] ?? 0,
                'previous_clicks' => $p['clicks'],
                'position' => $currentKeys->get($p['query'])['position'] ?? null,
                'previous_position' => $p['position'],
            ])
            ->map(fn ($r) => $r + ['click_change' => $r['clicks'] - $r['previous_clicks']])
            ->filter(fn ($r) => $r['click_change'] < 0)->sortBy('click_change')->take(10)->values();

        $brandClicks = $queries->where('brand', true)->sum('clicks');
        $queryClicks = $queries->sum('clicks');
        $topics = $this->topics($queries, $prevQueries->values(), $studentsByExamType);
        $intents = $queries->groupBy(fn ($r) => $r['intent'] ?? 'other')
            ->map(fn ($rows, $key) => [
                'key' => $key,
                'label' => TopicClassifier::intentLabel($key === 'other' ? null : $key),
                'queries' => $rows->count(),
                'clicks' => $rows->sum('clicks'),
                'impressions' => $rows->sum('impressions'),
                'ctr' => Insight::share($rows->sum('clicks'), $rows->sum('impressions')),
            ])->sortByDesc('impressions')->values();

        $devices = collect($raw['devices'] ?? [])->map(fn ($r) => [
            'device' => strtolower($r['keys'][0]), 'clicks' => $r['clicks'], 'impressions' => $r['impressions'],
            'ctr' => round($r['ctr'] * 100, 2), 'position' => round($r['position'], 1),
        ])->sortByDesc('clicks')->values();
        $countries = collect($raw['countries'] ?? [])->map(fn ($r) => [
            'country' => strtoupper($r['keys'][0]), 'clicks' => $r['clicks'], 'impressions' => $r['impressions'],
        ])->sortByDesc('clicks')->values();
        $pages = collect($raw['pages'] ?? [])->map(fn ($r) => [
            'page' => $r['keys'][0], 'clicks' => $r['clicks'], 'impressions' => $r['impressions'],
            'ctr' => round($r['ctr'] * 100, 2), 'position' => round($r['position'], 1),
        ])->sortByDesc('clicks')->take(25)->values();

        $data = [
            'kpis' => $kpis,
            'series' => collect($raw['series'] ?? [])->map(fn ($r) => [
                'date' => $r['keys'][0], 'clicks' => $r['clicks'], 'impressions' => $r['impressions'],
                'position' => round($r['position'], 1),
            ])->sortBy('date')->values(),
            'brand' => [
                'brand_clicks' => $brandClicks,
                'non_brand_clicks' => $queryClicks - $brandClicks,
                'brand_share' => Insight::share($brandClicks, $queryClicks),
                // Google hides rare queries for privacy; this is the visible part of all clicks.
                'visible_click_share' => Insight::share($queryClicks, $totals['clicks'] ?? 0),
            ],
            'top_queries' => $queries->sortByDesc('clicks')->take(30)->values(),
            'striking_distance' => $striking,
            'low_ctr' => $lowCtr,
            'rising' => $rising,
            'new_queries' => $newQueries,
            'declining' => $declining,
            'topics' => $topics,
            'intents' => $intents,
            'pages' => $pages,
            'devices' => $devices,
            'countries' => $countries,
        ];

        return $data + ['insights' => $this->insights($data)];
    }

    private function queryRows(array $rows, TopicClassifier $classifier): array
    {
        return array_map(fn ($r) => [
            'query' => $r['keys'][0],
            'clicks' => (int) $r['clicks'],
            'impressions' => (int) $r['impressions'],
            'ctr' => round($r['ctr'] * 100, 2),
            'position' => round($r['position'], 1),
            'topic' => $classifier->topic($r['keys'][0]),
            'intent' => $classifier->intent($r['keys'][0]),
            'brand' => TopicClassifier::isBrand($r['keys'][0]),
        ], $rows);
    }

    private function topics($queries, $prevQueries, array $studentsByExamType): array
    {
        $prevByTopic = $prevQueries->groupBy(fn ($r) => $r['topic'] ?? 'other');
        $totalStudents = array_sum($studentsByExamType);
        $classified = $queries->whereNotNull('topic');
        $classifiedImpressions = max(1, $classified->sum('impressions'));
        $classifiedStudents = 0;
        foreach (config('marketing.insights.topics', []) as $cfg) {
            foreach ($cfg['exam_type_ids'] ?? [] as $id) {
                $classifiedStudents += $studentsByExamType[$id] ?? 0;
            }
        }

        return $queries->groupBy(fn ($r) => $r['topic'] ?? 'other')
            ->map(function ($rows, $key) use ($prevByTopic, $studentsByExamType, $classifiedImpressions, $classifiedStudents, $totalStudents) {
                $impressions = $rows->sum('impressions');
                $prevClicks = isset($prevByTopic[$key]) ? $prevByTopic[$key]->sum('clicks') : 0;
                $students = null;
                if ($key !== 'other' && $totalStudents > 0) {
                    $students = 0;
                    foreach (config("marketing.insights.topics.$key.exam_type_ids", []) as $id) {
                        $students += $studentsByExamType[$id] ?? 0;
                    }
                }

                return [
                    'key' => $key,
                    'label' => TopicClassifier::topicLabel($key === 'other' ? null : $key),
                    'queries' => $rows->count(),
                    'clicks' => $rows->sum('clicks'),
                    'previous_clicks' => $prevClicks,
                    'click_change_pct' => Insight::changePct($rows->sum('clicks'), $prevClicks ?: null),
                    'impressions' => $impressions,
                    'ctr' => Insight::share($rows->sum('clicks'), $impressions),
                    // Impression-weighted, so one big query dominates as it does in Google.
                    'position' => $impressions > 0 ? round($rows->sum(fn ($r) => $r['position'] * $r['impressions']) / $impressions, 1) : null,
                    // Share among classified topics: search demand vs our student base.
                    'search_share' => $key === 'other' ? null : Insight::share($impressions, $classifiedImpressions),
                    'students' => $students,
                    'student_share' => $students === null || $classifiedStudents === 0 ? null : Insight::share($students, $classifiedStudents),
                ];
            })
            ->sortByDesc('impressions')->values()->all();
    }

    private function expectedCtr(float $position): float
    {
        return self::EXPECTED_CTR[max(1, min(10, (int) round($position)))];
    }

    private function studentsByExamType(): array
    {
        return DB::table('student_profiles')->whereNotNull('exam_type_id')
            ->groupBy('exam_type_id')->pluck(DB::raw('count(*)'), 'exam_type_id')
            ->map(fn ($n) => (int) $n)->all();
    }

    private function insights(array $d): array
    {
        $out = [];
        $clicks = $d['kpis']['clicks'];
        if ($clicks['change_pct'] !== null && abs($clicks['change_pct']) >= 15) {
            $up = $clicks['change_pct'] > 0;
            $out[] = Insight::make('search', $up ? 'win' : 'warning',
                'Google search clicks '.($up ? 'grew' : 'fell').' '.Insight::signed($clicks['change_pct']),
                Insight::int($clicks['value']).' clicks vs '.Insight::int($clicks['previous']).' in the previous period.',
                $up ? 'See "Rising queries" for what drove it and publish more on those topics.'
                    : 'Check "Declining queries" — a page may have lost rank, or the exam season ended.',
                $up ? 40 : 85);
        }

        $striking = collect($d['striking_distance']);
        if ($striking->isNotEmpty()) {
            $examples = $striking->take(3)->pluck('query')->map(fn ($q) => "\"$q\"")->implode(', ');
            $out[] = Insight::make('search', 'opportunity',
                $striking->count().' searches are close to page 1',
                "These rank 8–20 with real demand, e.g. $examples. Reaching the top 3 could add ~".Insight::int($striking->sum('potential_clicks')).' clicks per period.',
                'Improve the matching pages: put the exact phrase in the title and H1, add a free MCQ set or answer section, and link to them from the home page and related posts.',
                90);
        }

        $lowCtr = collect($d['low_ctr']);
        if ($lowCtr->isNotEmpty()) {
            $out[] = Insight::make('search', 'opportunity',
                $lowCtr->count().' well-ranked searches get few clicks',
                'They show on page 1 but CTR is under half of normal for their position (e.g. "'.$lowCtr->first()['query'].'" at #'.$lowCtr->first()['position'].' with '.Insight::pct($lowCtr->first()['ctr']).' CTR).',
                'Rewrite the title and meta description: lead with the benefit ("Free 2026 MCQs with answers"), include the year and the exam name.',
                80);
        }

        $brand = $d['brand'];
        if ($brand['non_brand_clicks'] + $brand['brand_clicks'] > 50) {
            if ($brand['brand_share'] >= 50) {
                $out[] = Insight::make('search', 'warning',
                    Insight::pct($brand['brand_share']).' of search clicks are people searching our name',
                    'Most search traffic comes from students who already know ExamsNepal; Google is sending few new students.',
                    'Create landing pages for generic searches (e.g. "nursing loksewa model questions", "NMCLE MCQ") — see Topics and Striking distance.',
                    75);
            } elseif ($brand['brand_share'] < 10) {
                $out[] = Insight::make('search', 'info',
                    'Only '.Insight::pct($brand['brand_share']).' of search clicks are brand searches',
                    'Discovery works, but few people look for ExamsNepal by name — brand recall is low.',
                    'Put the brand in Facebook creatives and video watermarks, and ask students to search "ExamsNepal" in posts.',
                    35);
            }
        }

        $topics = collect($d['topics'])->where('key', '!=', 'other');
        $growing = $topics->filter(fn ($t) => $t['click_change_pct'] !== null && $t['click_change_pct'] >= 25 && $t['clicks'] >= 20)->sortByDesc('click_change_pct')->first();
        if ($growing) {
            $out[] = Insight::make('search', 'opportunity',
                "Search demand for {$growing['label']} is rising (".Insight::signed($growing['click_change_pct']).')',
                Insight::int($growing['clicks']).' clicks this period vs '.Insight::int($growing['previous_clicks']).' before — likely an upcoming exam or notice.',
                "Run a Facebook push and an email to {$growing['label']} students now; feature their mock tests on the home page while demand is high.",
                70);
        }
        $underCaptured = $topics->filter(fn ($t) => $t['student_share'] !== null && $t['search_share'] >= 10 && $t['search_share'] >= $t['student_share'] * 2)->sortByDesc('search_share')->first();
        if ($underCaptured) {
            $out[] = Insight::make('search', 'opportunity',
                "{$underCaptured['label']} is under-captured",
                "It is ".Insight::pct($underCaptured['search_share'])." of our search impressions but only ".Insight::pct($underCaptured['student_share'])." of registered students — people find us but don't sign up.",
                "Check the {$underCaptured['label']} landing pages: add a clear \"Start free mock test\" sign-up CTA and make sure enough questions exist for that exam.",
                65);
        }
        $weak = $topics->filter(fn ($t) => $t['impressions'] >= 500 && $t['position'] !== null && $t['position'] > 15)->sortByDesc('impressions')->first();
        if ($weak) {
            $out[] = Insight::make('search', 'opportunity',
                "Content gap: {$weak['label']}",
                Insight::int($weak['impressions'])." impressions but an average position of {$weak['position']} — demand is there, our pages aren't ranking.",
                "Publish dedicated pages/blog posts for {$weak['label']} (syllabus, old questions, free MCQs) and interlink them.",
                60);
        }

        $intents = collect($d['intents'])->where('key', '!=', 'other');
        $topIntent = $intents->first();
        if ($topIntent && $topIntent['impressions'] > 0) {
            $out[] = Insight::make('search', 'info',
                "Students mostly search for: {$topIntent['label']}",
                Insight::int($topIntent['impressions']).' impressions across '.$topIntent['queries'].' queries.',
                'Shape Facebook posts and page titles around this need — it is the language students use.',
                30);
        }

        $devices = collect($d['devices']);
        $mobile = $devices->firstWhere('device', 'mobile');
        if ($mobile && $devices->sum('clicks') > 0 && Insight::share($mobile['clicks'], $devices->sum('clicks')) >= 70) {
            $out[] = Insight::make('search', 'info',
                Insight::pct(Insight::share($mobile['clicks'], $devices->sum('clicks'))).' of search clicks are on mobile',
                'Students find us on their phones.',
                'Show a "Get the app" banner on mobile landing pages and keep pages fast on 4G.',
                25);
        }

        $countries = collect($d['countries']);
        $abroad = $countries->where('country', '!=', 'NPL')->sum('clicks');
        if ($countries->sum('clicks') > 50 && Insight::share($abroad, $countries->sum('clicks')) >= 10) {
            $out[] = Insight::make('search', 'info',
                Insight::pct(Insight::share($abroad, $countries->sum('clicks'))).' of search clicks come from outside Nepal',
                'Top: '.$countries->where('country', '!=', 'NPL')->take(3)->pluck('country')->implode(', ').'.',
                'Nepali students abroad prepare for exams back home — consider content for license exams taken after studying abroad.',
                20);
        }

        return $out;
    }
}
