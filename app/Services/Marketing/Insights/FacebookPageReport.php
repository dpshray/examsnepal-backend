<?php

namespace App\Services\Marketing\Insights;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Facebook Page: audience size, what we post, and which formats, topics and
 * times get students to react, comment and share.
 *
 * Post engagement (reactions + comments + shares) comes from the posts
 * themselves, which is stable across Graph API versions; Page Insights
 * metrics are best-effort (config marketing.insights.facebook.page_metrics).
 */
class FacebookPageReport
{
    private const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /** Page Insights accepts at most ~93 days per request. */
    private const MAX_INSIGHT_DAYS = 90;

    public function __construct(private CarbonImmutable $from, private CarbonImmutable $to) {}

    public static function configured(): bool
    {
        return (bool) config('marketing.insights.facebook.page_id') && (bool) config('marketing.insights.facebook.page_token');
    }

    public function build(): array
    {
        [$prevFrom] = Insight::previousPeriod($this->from, $this->to);
        $page = $this->get(config('marketing.insights.facebook.page_id'), [
            'fields' => 'name,fan_count,followers_count,link,picture{url}',
        ]);

        $posts = $this->posts($prevFrom->startOfDay(), $this->to->endOfDay());

        return $this->analyze($page, $posts, $this->pageMetrics());
    }

    private function posts(CarbonImmutable $since, CarbonImmutable $until): array
    {
        $max = (int) config('marketing.insights.facebook.max_posts', 300);
        $posts = [];
        $response = $this->get(config('marketing.insights.facebook.page_id').'/posts', [
            'fields' => 'id,message,created_time,permalink_url,status_type,full_picture,shares,'
                .'reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0),attachments.limit(1){media_type}',
            'since' => $since->timestamp,
            'until' => $until->timestamp,
            'limit' => 100,
        ]);
        while (true) {
            array_push($posts, ...($response['data'] ?? []));
            $next = $response['paging']['next'] ?? null;
            if (! $next || count($posts) >= $max) {
                break;
            }
            $response = $this->request(Http::timeout(30)->get($next));
        }

        return array_slice($posts, 0, $max);
    }

    /** @return array<string, array{values?: array, error?: string}> */
    private function pageMetrics(): array
    {
        $since = CarbonImmutable::parse(max($this->from->toDateString(), $this->to->subDays(self::MAX_INSIGHT_DAYS - 1)->toDateString()));
        $pageId = config('marketing.insights.facebook.page_id');
        $metrics = array_keys(config('marketing.insights.facebook.page_metrics', []));
        $responses = Http::pool(fn (Pool $pool) => array_map(fn ($metric) => $pool->as($metric)->timeout(30)
            ->get($this->url("$pageId/insights"), [
                'metric' => $metric,
                'period' => 'day',
                'since' => $since->toDateString(),
                // "until" is exclusive.
                'until' => $this->to->addDay()->toDateString(),
                'access_token' => config('marketing.insights.facebook.page_token'),
            ]), $metrics));

        $out = [];
        foreach ($metrics as $metric) {
            $response = $responses[$metric];
            if ($response instanceof \Throwable || $response->failed()) {
                $out[$metric] = ['error' => $response instanceof \Throwable ? $response->getMessage() : ($response->json('error.message') ?? 'unavailable')];
                continue;
            }
            $out[$metric] = ['values' => $response->json('data.0.values') ?? [], 'since' => $since->toDateString()];
        }

        return $out;
    }

    /**
     * Pure analysis (testable without Facebook).
     *
     * @param  array  $page  page node fields
     * @param  array  $posts  raw posts covering the previous and current period
     * @param  array  $metrics  pageMetrics() output
     */
    public function analyze(array $page, array $posts, array $metrics): array
    {
        $tz = config('marketing.insights.timezone', 'Asia/Kathmandu');
        $classifier = new TopicClassifier();
        $from = $this->from->startOfDay();

        $all = collect($posts)->map(function ($p) use ($tz, $classifier) {
            $at = CarbonImmutable::parse($p['created_time'])->setTimezone($tz);
            $reactions = (int) ($p['reactions']['summary']['total_count'] ?? 0);
            $comments = (int) ($p['comments']['summary']['total_count'] ?? 0);
            $shares = (int) ($p['shares']['count'] ?? 0);

            return [
                'id' => $p['id'],
                'message' => Str::limit(trim((string) ($p['message'] ?? '')), 280),
                'created_at' => $at->toIso8601String(),
                'day' => $at->dayOfWeek,
                'hour' => $at->hour,
                'format' => $this->format($p),
                'topic' => $classifier->topic($p['message'] ?? null),
                'permalink' => $p['permalink_url'] ?? null,
                'image' => $p['full_picture'] ?? null,
                'reactions' => $reactions,
                'comments' => $comments,
                'shares' => $shares,
                'engagement' => $reactions + $comments + $shares,
                '_at' => $at,
            ];
        });
        $current = $all->filter(fn ($p) => $p['_at']->gte($from))->values();
        $previous = $all->filter(fn ($p) => $p['_at']->lt($from))->values();

        $followers = (int) ($page['followers_count'] ?? $page['fan_count'] ?? 0);
        $avg = fn ($posts) => $posts->count() ? round($posts->avg('engagement'), 1) : null;
        $weeks = max(1, ($this->from->diffInDays($this->to) + 1) / 7);

        $group = fn (callable $key, callable $label) => $current->groupBy($key)->map(fn ($posts, $k) => [
            'key' => (string) $k,
            'label' => $label($k),
            'posts' => $posts->count(),
            'avg_engagement' => round($posts->avg('engagement'), 1),
            'avg_comments' => round($posts->avg('comments'), 1),
            'avg_shares' => round($posts->avg('shares'), 1),
        ])->sortByDesc('avg_engagement')->values();

        $insightSeries = [];
        $unavailable = [];
        foreach ($metrics as $metric => $result) {
            if (isset($result['error'])) {
                $unavailable[] = ['metric' => $metric, 'error' => $result['error']];
                continue;
            }
            $values = collect($result['values'])->map(fn ($v) => [
                // end_time is the end of the day in Pacific time; the day before it is the measured day.
                'date' => CarbonImmutable::parse($v['end_time'])->subDay()->toDateString(),
                'value' => is_numeric($v['value']) ? (int) $v['value'] : 0,
            ])->values();
            $insightSeries[$metric] = [
                'label' => config("marketing.insights.facebook.page_metrics.$metric", $metric),
                'total' => $values->sum('value'),
                'since' => $result['since'] ?? null,
                'series' => $values,
            ];
        }

        $strip = fn ($p) => collect($p)->except('_at')->all();
        $data = [
            'page' => [
                'name' => $page['name'] ?? null,
                'link' => $page['link'] ?? null,
                'picture' => $page['picture']['data']['url'] ?? null,
                'followers' => $followers,
                'likes' => isset($page['fan_count']) ? (int) $page['fan_count'] : null,
            ],
            'kpis' => [
                'posts' => Insight::kpi($current->count(), $previous->count()),
                'engagement' => Insight::kpi($current->sum('engagement'), $previous->sum('engagement')),
                'avg_engagement' => Insight::kpi($avg($current), $avg($previous)),
                'comments' => Insight::kpi($current->sum('comments'), $previous->sum('comments')),
                'shares' => Insight::kpi($current->sum('shares'), $previous->sum('shares')),
                // Average engagement per post per 1,000 followers - comparable across page sizes.
                'engagement_per_1k' => Insight::kpi(
                    $followers && $current->count() ? round($avg($current) / $followers * 1000, 2) : null,
                    $followers && $previous->count() ? round($avg($previous) / $followers * 1000, 2) : null,
                ),
            ],
            'posts_per_week' => round($current->count() / $weeks, 1),
            'longest_gap_days' => $this->longestGap($current),
            'by_format' => $group(fn ($p) => $p['format'], fn ($k) => ucfirst($k)),
            'by_topic' => $group(fn ($p) => $p['topic'] ?? 'other', fn ($k) => TopicClassifier::topicLabel($k === 'other' ? null : $k)),
            'by_day' => $group(fn ($p) => $p['day'], fn ($k) => self::DAYS[(int) $k]),
            'by_hour' => $group(fn ($p) => $p['hour'], fn ($k) => sprintf('%02d:00', $k))->sortBy(fn ($r) => (int) $r['key'])->values(),
            'series' => $current->groupBy(fn ($p) => $p['_at']->toDateString())->map(fn ($posts, $date) => [
                'date' => $date, 'posts' => $posts->count(), 'engagement' => $posts->sum('engagement'),
            ])->sortKeys()->values(),
            'top_posts' => $current->sortByDesc('engagement')->take(8)->map($strip)->values(),
            'weakest_posts' => $current->count() >= 10 ? $current->sortBy('engagement')->take(5)->map($strip)->values() : [],
            'page_insights' => $insightSeries,
            'unavailable_metrics' => $unavailable,
        ];

        return $data + ['insights' => $this->insights($data)];
    }

    private function format(array $post): string
    {
        $media = $post['attachments']['data'][0]['media_type'] ?? null;

        return match (true) {
            in_array($media, ['video', 'reel'], true) || str_contains((string) ($post['status_type'] ?? ''), 'video') => 'video',
            $media === 'album' => 'album',
            $media === 'photo' => 'photo',
            $media === 'link' || ($post['status_type'] ?? null) === 'shared_story' => 'link',
            $media === 'event' => 'event',
            default => 'text',
        };
    }

    private function longestGap($posts): ?int
    {
        $dates = $posts->pluck('_at')->push($this->from->startOfDay())->push(CarbonImmutable::now()->min($this->to->endOfDay()))
            ->sort()->values();
        $gap = 0;
        for ($i = 1; $i < $dates->count(); $i++) {
            $gap = max($gap, (int) $dates[$i - 1]->diffInDays($dates[$i]));
        }

        return $posts->isEmpty() ? null : $gap;
    }

    private function insights(array $d): array
    {
        $out = [];
        $k = $d['kpis'];
        $posts = $k['posts']['value'];

        if ($posts === 0) {
            return [Insight::make('facebook', 'warning', 'No Facebook posts in this period',
                'The page was silent, so followers saw nothing from ExamsNepal.',
                'Start with 4–5 posts a week: an MCQ of the day, exam notices, and results/success stories.', 95)];
        }

        if ($d['posts_per_week'] < 3) {
            $out[] = Insight::make('facebook', 'warning',
                "Posting only {$d['posts_per_week']} times a week",
                'Facebook shows pages that post regularly to more of their followers.'.($d['longest_gap_days'] >= 7 ? " The longest silence was {$d['longest_gap_days']} days." : ''),
                'Aim for one post a day. Batch-create a week of "MCQ of the day" posts (question in the image, answer in the comments next day).',
                75);
        } elseif ($d['longest_gap_days'] !== null && $d['longest_gap_days'] >= 7) {
            $out[] = Insight::make('facebook', 'warning',
                "A {$d['longest_gap_days']}-day gap without posts",
                'Reach drops after long pauses and takes time to recover.',
                'Keep a buffer of scheduled posts in Meta Business Suite for busy weeks.',
                50);
        }

        $eng = $k['avg_engagement'];
        if ($eng['change_pct'] !== null && abs($eng['change_pct']) >= 20 && $k['posts']['previous'] >= 3) {
            $up = $eng['change_pct'] > 0;
            $out[] = Insight::make('facebook', $up ? 'win' : 'warning',
                'Engagement per post '.($up ? 'up' : 'down').' '.Insight::signed($eng['change_pct']),
                "{$eng['value']} interactions per post vs {$eng['previous']} before.",
                $up ? 'Look at the top posts below and repeat their format and topic.'
                    : 'Compare the top and weakest posts: change the format or topic mix, and ask a question in every post.',
                $up ? 45 : 70);
        }

        $formats = collect($d['by_format'])->filter(fn ($f) => $f['posts'] >= 2);
        if ($formats->count() >= 2) {
            $best = $formats->first();
            $worst = $formats->last();
            if ($worst['avg_engagement'] > 0 && $best['avg_engagement'] >= $worst['avg_engagement'] * 1.5) {
                $out[] = Insight::make('facebook', 'opportunity',
                    "{$best['label']} posts get ".round($best['avg_engagement'] / $worst['avg_engagement'], 1).'× the engagement of '.strtolower($worst['label']).' posts',
                    "{$best['avg_engagement']} vs {$worst['avg_engagement']} interactions per post ({$best['posts']} and {$worst['posts']} posts).",
                    "Shift the mix towards {$best['label']} posts.".($best['key'] === 'video' ? ' Short reels explaining one tricky MCQ work well for exam prep.' : ''),
                    65);
            }
        }

        $topics = collect($d['by_topic'])->filter(fn ($t) => $t['posts'] >= 2 && $t['key'] !== 'other');
        if ($topics->isNotEmpty() && $eng['value']) {
            $bestTopic = $topics->first();
            if ($bestTopic['avg_engagement'] >= $eng['value'] * 1.3) {
                $out[] = Insight::make('facebook', 'win',
                    "{$bestTopic['label']} posts perform best",
                    "{$bestTopic['avg_engagement']} interactions per post vs {$eng['value']} average.",
                    "This audience is most active on Facebook — post more for {$bestTopic['label']} and use it to promote its mock tests.",
                    55);
            }
        }

        $days = collect($d['by_day'])->filter(fn ($r) => $r['posts'] >= 2);
        $hours = collect($d['by_hour'])->filter(fn ($r) => $r['posts'] >= 2)->sortByDesc('avg_engagement');
        if ($posts >= 8 && $days->count() >= 3 && $hours->count() >= 2) {
            $out[] = Insight::make('facebook', 'info',
                'Best time to post: '.$days->first()['label'].'s around '.$hours->first()['label'],
                "Posts then average {$days->first()['avg_engagement']} interactions (by day) and {$hours->first()['avg_engagement']} (by hour), Nepal time.",
                'Schedule important posts (new mock tests, offers) in this slot. Cross-check with the website activity heatmap.',
                50);
        }

        if ($k['engagement']['value'] > 20) {
            $commentShare = Insight::share($k['comments']['value'], $k['engagement']['value']);
            if ($commentShare < 10) {
                $out[] = Insight::make('facebook', 'opportunity',
                    'Few comments ('.Insight::pct($commentShare).' of interactions)',
                    'Followers like posts but rarely comment — comments push posts to more people.',
                    'Post MCQs and ask "Comment your answer (A/B/C/D)"; reply to every comment within the first hour.',
                    60);
            }
            $shareShare = Insight::share($k['shares']['value'], $k['engagement']['value']);
            if ($shareShare >= 15) {
                $out[] = Insight::make('facebook', 'win',
                    'Students share our posts ('.Insight::pct($shareShare).' of interactions)',
                    'Shares bring free reach to friends preparing for the same exam.',
                    'Make more share-worthy content: syllabus summaries, exam date notices, "save this" cheat sheets.',
                    40);
            }
        }

        if ($k['engagement_per_1k']['value'] !== null && $d['page']['followers'] >= 1000 && $k['engagement_per_1k']['value'] < 1) {
            $out[] = Insight::make('facebook', 'warning',
                'Low engagement for the page size',
                "{$k['engagement_per_1k']['value']} interactions per post per 1,000 followers — most followers are not seeing or reacting to posts.",
                'Post content that asks for action (polls, MCQs), and run small boosted posts to re-engage followers.',
                55);
        }

        $follows = $d['page_insights']['page_daily_follows_unique']['total'] ?? null;
        $unfollows = $d['page_insights']['page_daily_unfollows_unique']['total'] ?? null;
        if ($follows !== null && $unfollows !== null && $unfollows > $follows) {
            $out[] = Insight::make('facebook', 'warning',
                'The page is losing followers',
                Insight::int($unfollows).' unfollows vs '.Insight::int($follows).' new followers.',
                'Check recent posts for off-topic or too-salesy content; keep the ratio about 4 helpful posts to 1 promotional.',
                70);
        }

        return $out;
    }

    private function get(string $path, array $query): array
    {
        return $this->request(Http::timeout(30)->get($this->url($path), $query + [
            'access_token' => config('marketing.insights.facebook.page_token'),
        ]));
    }

    private function request($response): array
    {
        if ($response->failed()) {
            throw new RuntimeException('Facebook: '.($response->json('error.message') ?? "HTTP {$response->status()}"));
        }

        return $response->json() ?? [];
    }

    private function url(string $path): string
    {
        return 'https://graph.facebook.com/'.config('marketing.insights.facebook.graph_version').'/'.ltrim($path, '/');
    }
}
