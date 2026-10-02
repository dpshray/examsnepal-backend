<?php

namespace App\Services\Marketing\Insights;

use Carbon\CarbonImmutable;

/**
 * Google Analytics 4: where visitors come from, which channels and pages
 * engage, and when students are online.
 */
class AnalyticsReport
{
    private const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function __construct(private CarbonImmutable $from, private CarbonImmutable $to) {}

    public static function configured(): bool
    {
        return GoogleApi::hasCredentials() && (bool) config('marketing.insights.ga4_property_id');
    }

    public function build(): array
    {
        [$prevFrom, $prevTo] = Insight::previousPeriod($this->from, $this->to);
        $url = 'https://analyticsdata.googleapis.com/v1beta/properties/'.config('marketing.insights.ga4_property_id').':runReport';
        $current = ['startDate' => $this->from->toDateString(), 'endDate' => $this->to->toDateString(), 'name' => 'current'];
        $previous = ['startDate' => $prevFrom->toDateString(), 'endDate' => $prevTo->toDateString(), 'name' => 'previous'];
        $report = fn (array $dims, array $metrics, array $extra = [], bool $compare = false) => [$url, array_merge([
            'dateRanges' => $compare ? [$current, $previous] : [$current],
            'dimensions' => array_map(fn ($d) => ['name' => $d], $dims),
            'metrics' => array_map(fn ($m) => ['name' => $m], $metrics),
        ], $extra)];
        $bySessions = ['orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]]];

        $requests = [
            'totals' => $report([], ['activeUsers', 'newUsers', 'sessions', 'engagementRate', 'averageSessionDuration', 'screenPageViews', 'keyEvents'], [], true),
            'series' => $report(['date'], ['activeUsers', 'newUsers', 'sessions'], ['orderBys' => [['dimension' => ['dimensionName' => 'date']]]]),
            'channels' => $report(['sessionDefaultChannelGroup'], ['sessions', 'newUsers', 'engagementRate', 'averageSessionDuration', 'keyEvents'], $bySessions, true),
            'sources' => $report(['sessionSourceMedium'], ['sessions', 'newUsers', 'engagementRate', 'keyEvents'], $bySessions + ['limit' => 20]),
            'landing' => $report(['landingPage'], ['sessions', 'newUsers', 'engagementRate', 'keyEvents'], $bySessions + ['limit' => 30]),
            'cities' => $report(['city'], ['activeUsers', 'sessions'], ['orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]], 'limit' => 15]),
            'countries' => $report(['country'], ['activeUsers'], ['orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]], 'limit' => 10]),
            'devices' => $report(['deviceCategory'], ['activeUsers', 'sessions', 'engagementRate']),
            'heatmap' => $report(['dayOfWeek', 'hour'], ['activeUsers'], ['limit' => 200]),
            'events' => $report(['eventName'], ['eventCount', 'keyEvents'], ['orderBys' => [['metric' => ['metricName' => 'eventCount'], 'desc' => true]], 'limit' => 25]),
        ];
        // The API takes at most ~10 concurrent requests per property comfortably; two waves.
        $api = new GoogleApi();
        $raw = $api->postMany(array_slice($requests, 0, 5, true)) + $api->postMany(array_slice($requests, 5, null, true));

        return $this->analyze(array_map(fn ($r) => $this->rows($r), $raw));
    }

    /** Flatten a runReport response into rows keyed by header name (+ "range" when comparing periods). */
    private function rows(array $response): array
    {
        $dims = array_column($response['dimensionHeaders'] ?? [], 'name');
        $metrics = array_column($response['metricHeaders'] ?? [], 'name');

        return array_map(function ($row) use ($dims, $metrics) {
            $out = [];
            $values = array_column($row['dimensionValues'] ?? [], 'value');
            foreach ($dims as $i => $name) {
                $out[$name] = $values[$i] ?? null;
            }
            // With two date ranges Google adds a "dateRange" dimension holding the range name.
            $out['range'] = $out['dateRange'] ?? (count($values) > count($dims) ? end($values) : 'current');
            foreach ($metrics as $i => $name) {
                $out[$name] = (float) ($row['metricValues'][$i]['value'] ?? 0);
            }

            return $out;
        }, $response['rows'] ?? []);
    }

    /** Pure analysis over flattened GA4 rows (testable without Google). */
    public function analyze(array $raw): array
    {
        $totals = collect($raw['totals'] ?? []);
        $cur = $totals->firstWhere('range', 'current') ?? [];
        $prev = $totals->firstWhere('range', 'previous');
        $k = fn (string $m, int $digits = 0, float $mul = 1) => Insight::kpi(
            isset($cur[$m]) ? round($cur[$m] * $mul, $digits) : 0,
            $prev && isset($prev[$m]) ? round($prev[$m] * $mul, $digits) : null,
        );

        $channelRows = collect($raw['channels'] ?? []);
        $channelPrev = $channelRows->where('range', 'previous')->keyBy('sessionDefaultChannelGroup');
        $channelSessions = max(1, $channelRows->where('range', 'current')->sum('sessions'));
        $channels = $channelRows->where('range', 'current')->map(fn ($r) => [
            'channel' => $r['sessionDefaultChannelGroup'],
            'sessions' => (int) $r['sessions'],
            'previous_sessions' => (int) ($channelPrev->get($r['sessionDefaultChannelGroup'])['sessions'] ?? 0),
            'change_pct' => Insight::changePct($r['sessions'], $channelPrev->get($r['sessionDefaultChannelGroup'])['sessions'] ?? null),
            'share' => Insight::share($r['sessions'], $channelSessions),
            'new_users' => (int) $r['newUsers'],
            'engagement_rate' => round($r['engagementRate'] * 100, 1),
            'avg_session_seconds' => (int) round($r['averageSessionDuration']),
            'key_events' => (int) $r['keyEvents'],
            'key_event_rate' => Insight::share($r['keyEvents'], $r['sessions']),
        ])->sortByDesc('sessions')->values();

        $heat = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($raw['heatmap'] ?? [] as $r) {
            $heat[(int) $r['dayOfWeek']][(int) $r['hour']] += (int) $r['activeUsers'];
        }

        $data = [
            'kpis' => [
                'active_users' => $k('activeUsers'),
                'new_users' => $k('newUsers'),
                'sessions' => $k('sessions'),
                'engagement_rate' => $k('engagementRate', 1, 100),
                'avg_session_seconds' => $k('averageSessionDuration'),
                'page_views' => $k('screenPageViews'),
                'key_events' => $k('keyEvents'),
            ],
            'series' => collect($raw['series'] ?? [])->map(fn ($r) => [
                'date' => substr($r['date'], 0, 4).'-'.substr($r['date'], 4, 2).'-'.substr($r['date'], 6, 2),
                'active_users' => (int) $r['activeUsers'],
                'new_users' => (int) $r['newUsers'],
                'sessions' => (int) $r['sessions'],
            ])->values(),
            'channels' => $channels,
            'sources' => collect($raw['sources'] ?? [])->map(fn ($r) => [
                'source' => $r['sessionSourceMedium'],
                'sessions' => (int) $r['sessions'],
                'new_users' => (int) $r['newUsers'],
                'engagement_rate' => round($r['engagementRate'] * 100, 1),
                'key_events' => (int) $r['keyEvents'],
            ])->values(),
            'landing_pages' => collect($raw['landing'] ?? [])->map(fn ($r) => [
                'page' => $r['landingPage'] ?: '(not set)',
                'sessions' => (int) $r['sessions'],
                'new_users' => (int) $r['newUsers'],
                'engagement_rate' => round($r['engagementRate'] * 100, 1),
                'key_events' => (int) $r['keyEvents'],
            ])->values(),
            'cities' => collect($raw['cities'] ?? [])->map(fn ($r) => [
                'city' => $r['city'], 'active_users' => (int) $r['activeUsers'], 'sessions' => (int) $r['sessions'],
            ])->values(),
            'countries' => collect($raw['countries'] ?? [])->map(fn ($r) => [
                'country' => $r['country'], 'active_users' => (int) $r['activeUsers'],
            ])->values(),
            'devices' => collect($raw['devices'] ?? [])->map(fn ($r) => [
                'device' => $r['deviceCategory'], 'active_users' => (int) $r['activeUsers'],
                'sessions' => (int) $r['sessions'], 'engagement_rate' => round($r['engagementRate'] * 100, 1),
            ])->sortByDesc('active_users')->values(),
            // [day 0=Sunday][hour 0-23] => active users, in the GA4 property's time zone.
            'heatmap' => $heat,
            'events' => collect($raw['events'] ?? [])->map(fn ($r) => [
                'event' => $r['eventName'], 'count' => (int) $r['eventCount'], 'is_key_event' => $r['keyEvents'] > 0,
            ])->values(),
        ];

        return $data + ['insights' => $this->insights($data)];
    }

    private function insights(array $d): array
    {
        $out = [];
        $users = $d['kpis']['active_users'];
        if ($users['change_pct'] !== null && abs($users['change_pct']) >= 15) {
            $up = $users['change_pct'] > 0;
            $out[] = Insight::make('analytics', $up ? 'win' : 'warning',
                'Website visitors '.($up ? 'up' : 'down').' '.Insight::signed($users['change_pct']),
                Insight::int($users['value']).' active users vs '.Insight::int($users['previous']).' in the previous period.',
                $up ? 'Check the Channels table to see which source grew and double down on it.'
                    : 'Compare channels with the previous period to find the source that dropped.',
                $up ? 40 : 85);
        }

        $channels = collect($d['channels']);
        $total = $channels->sum('sessions');
        if ($total >= 100) {
            $top = $channels->first();
            if ($top['share'] >= 60) {
                $out[] = Insight::make('analytics', 'warning',
                    "{$top['channel']} brings ".Insight::pct($top['share']).' of all visits',
                    'Relying on one channel is risky — a Google update or a Facebook reach drop would hit traffic hard.',
                    'Build a second channel: a weekly Facebook/YouTube content habit, or referral partnerships with colleges and coaching centres.',
                    60);
            }

            $social = $channels->filter(fn ($c) => str_contains(strtolower($c['channel']), 'social'));
            $socialShare = Insight::share($social->sum('sessions'), $total);
            if ($socialShare < 10) {
                $out[] = Insight::make('analytics', 'opportunity',
                    'Social media sends only '.Insight::pct($socialShare).' of visits',
                    'Facebook activity is not turning into website or app visits.',
                    'Add a link to a specific free mock test in every post (not just the home page), and use UTM tags (utm_source=facebook) so sign-ups are tracked.',
                    70);
            }

            $significant = $channels->filter(fn ($c) => $c['share'] >= 5);
            $best = $significant->filter(fn ($c) => $c['key_events'] > 0)->sortByDesc('key_event_rate')->first();
            if ($best && $significant->count() > 1) {
                $out[] = Insight::make('analytics', 'win',
                    "{$best['channel']} visitors convert best",
                    Insight::pct($best['key_event_rate'])." of {$best['channel']} sessions trigger a key event (sign-up, purchase…).",
                    "Spend more effort (or ad budget) on {$best['channel']}.",
                    55);
            }
            $worst = $significant->sortBy('engagement_rate')->first();
            $avgEngagement = $d['kpis']['engagement_rate']['value'];
            if ($worst && $avgEngagement && $worst['engagement_rate'] < $avgEngagement * 0.7) {
                $out[] = Insight::make('analytics', 'warning',
                    "{$worst['channel']} traffic barely engages",
                    "Engagement rate {$worst['engagement_rate']}% vs {$avgEngagement}% site average — those visitors leave quickly.",
                    'Point these links at a page that matches what was promised (e.g. a specific quiz), not the home page.',
                    50);
            }
        }

        $landing = collect($d['landing_pages']);
        $minSessions = max(30, (int) ($landing->sum('sessions') * 0.03));
        $leaky = $landing->filter(fn ($p) => $p['sessions'] >= $minSessions && $p['engagement_rate'] < 40)->sortByDesc('sessions')->take(3);
        if ($leaky->isNotEmpty()) {
            $out[] = Insight::make('analytics', 'warning',
                $leaky->count().' popular landing page'.($leaky->count() > 1 ? 's lose' : ' loses').' most visitors',
                $leaky->map(fn ($p) => "{$p['page']} ({$p['engagement_rate']}% engaged)")->implode(', ').'.',
                'Above the fold, show what the visitor searched for plus one clear button ("Start free quiz"); check mobile load speed.',
                65);
        }

        // When students are online: best 3-hour window and weekday.
        $heat = $d['heatmap'];
        $hourTotals = array_map(fn ($h) => array_sum(array_column($heat, $h)), range(0, 23));
        $dayTotals = array_map('array_sum', $heat);
        if (array_sum($hourTotals) >= 200) {
            $bestStart = 0;
            $bestSum = -1;
            for ($h = 0; $h < 24; $h++) {
                $sum = $hourTotals[$h] + $hourTotals[($h + 1) % 24] + $hourTotals[($h + 2) % 24];
                if ($sum > $bestSum) {
                    [$bestStart, $bestSum] = [$h, $sum];
                }
            }
            $bestDay = array_search(max($dayTotals), $dayTotals);
            $out[] = Insight::make('analytics', 'info',
                'Students are most active '.$this->hourLabel($bestStart).'–'.$this->hourLabel(($bestStart + 3) % 24).', busiest on '.self::DAYS[$bestDay],
                Insight::pct(Insight::share($bestSum, array_sum($hourTotals))).' of visits fall in that window.',
                'Schedule Facebook posts ~30 minutes before it, and send marketing emails/push notifications then.',
                55);
        }

        $devices = collect($d['devices']);
        $mobile = $devices->firstWhere('device', 'mobile');
        $deviceUsers = $devices->sum('active_users');
        if ($mobile && $deviceUsers > 0 && Insight::share($mobile['active_users'], $deviceUsers) >= 70) {
            $out[] = Insight::make('analytics', 'info',
                Insight::pct(Insight::share($mobile['active_users'], $deviceUsers)).' of web visitors use a phone',
                'The mobile web is the main shop window.',
                'Promote the Android app on mobile pages — app users come back more often.',
                25);
        }

        $kpis = $d['kpis'];
        if ($kpis['active_users']['value'] > 100) {
            $newShare = Insight::share($kpis['new_users']['value'], $kpis['active_users']['value']);
            if ($newShare >= 80) {
                $out[] = Insight::make('analytics', 'warning',
                    Insight::pct($newShare).' of visitors are new',
                    'Few visitors come back — acquisition works, retention does not.',
                    'Capture them on the first visit: push sign-up for a free mock result, then follow up by email/push (Email automation).',
                    60);
            } elseif ($newShare <= 30) {
                $out[] = Insight::make('analytics', 'info',
                    'Only '.Insight::pct($newShare).' of visitors are new',
                    'Loyal users, but little fresh reach.',
                    'Invest in reach: Facebook ads to new exam-takers, SEO pages, college partnerships.',
                    45);
            }
        }

        $cities = collect($d['cities'])->reject(fn ($c) => $c['city'] === '(not set)');
        if ($cities->sum('active_users') > 100) {
            $valley = ['Kathmandu', 'Lalitpur', 'Bhaktapur', 'Patan', 'Kirtipur'];
            $outside = $cities->reject(fn ($c) => in_array($c['city'], $valley))->take(3);
            $out[] = Insight::make('analytics', 'info',
                'Top cities: '.$cities->take(3)->pluck('city')->implode(', '),
                'Outside the valley, most visitors come from '.($outside->pluck('city')->implode(', ') ?: '—').'.',
                'Use these cities for Facebook ad geo-targeting and for college/coaching-centre partnerships.',
                30);
        }

        return $out;
    }

    private function hourLabel(int $hour): string
    {
        return $hour === 0 ? '12am' : ($hour < 12 ? "{$hour}am" : ($hour === 12 ? '12pm' : ($hour - 12).'pm'));
    }
}
