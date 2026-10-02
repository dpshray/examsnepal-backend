<?php

namespace App\Services\Marketing\Insights;

use App\Services\Notices\Enrichment\NoticeAiClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Entry point for the web & social insights page: fetches and caches each
 * source, adds cross-source insights, and writes the optional AI brief.
 */
class InsightsService
{
    public const SOURCES = [
        'search' => SearchConsoleReport::class,
        'analytics' => AnalyticsReport::class,
        'facebook' => FacebookPageReport::class,
    ];

    public function __construct(private CarbonImmutable $from, private CarbonImmutable $to) {}

    /**
     * @return array{configured: bool, data: ?array, error: ?string, fetched_at: ?string, setup: array}
     */
    public function source(string $source, bool $refresh = false): array
    {
        $class = self::SOURCES[$source];
        if (! $class::configured()) {
            return ['configured' => false, 'data' => null, 'error' => null, 'fetched_at' => null, 'setup' => $this->setup($source)];
        }

        $key = $this->key($source);
        if ($refresh) {
            Cache::forget($key);
        }
        // Errors are returned, not cached, so fixing a permission shows up on the next load.
        try {
            $cached = Cache::remember($key, config('marketing.insights.cache_minutes', 60) * 60, fn () => [
                'data' => (new $class($this->from, $this->to))->build(),
                'fetched_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            Log::warning("Marketing insights: $source failed", ['error' => $e->getMessage()]);

            return ['configured' => true, 'data' => null, 'error' => $e->getMessage(), 'fetched_at' => null, 'setup' => $this->setup($source)];
        }

        return ['configured' => true, 'error' => null, 'setup' => []] + $cached;
    }

    /** Cross-source insights plus every source's insights, most important first. Only reads cache. */
    public function summary(): array
    {
        $data = [];
        $status = [];
        foreach (array_keys(self::SOURCES) as $source) {
            $data[$source] = Cache::get($this->key($source))['data'] ?? null;
            $status[$source] = ['configured' => self::SOURCES[$source]::configured(), 'loaded' => $data[$source] !== null];
        }
        $signups = $this->signupSources();

        $insights = collect($data)->filter()->flatMap(fn ($d) => $d['insights'] ?? [])
            ->merge($this->crossInsights($data, $signups))
            ->sortByDesc('priority')->values();

        return ['sources' => $status, 'insights' => $insights, 'signup_sources' => $signups];
    }

    /** AI-written marketing plan from the cached source data. */
    public function brief(bool $refresh = false): array
    {
        if (! NoticeAiClient::isConfigured()) {
            return ['available' => false, 'reason' => 'Set NOTICES_AI_PROVIDER and its API key in .env to enable the AI brief.'];
        }
        $key = "marketing:insights:brief:{$this->from->toDateString()}:{$this->to->toDateString()}";
        if ($refresh) {
            Cache::forget($key);
        }
        if ($cached = Cache::get($key)) {
            return ['available' => true] + $cached;
        }

        foreach (array_keys(self::SOURCES) as $source) {
            $this->source($source); // warm the cache
        }
        $digest = $this->digest();
        if (count($digest['sources']) === 0 && $digest['signups'] === []) {
            return ['available' => false, 'reason' => 'Connect at least one source (Search Console, Analytics or Facebook) first.'];
        }

        $model = config('marketing.insights.ai_model') ?: config('notices.ai.model');
        $result = app(NoticeAiClient::class)->extract($model, $this->briefPrompt(), [
            ['type' => 'text', 'text' => json_encode($digest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ], self::BRIEF_SCHEMA);
        if (! is_array($result['data'])) {
            throw new \RuntimeException('The AI returned an unreadable answer; try again.');
        }

        $brief = ['brief' => $result['data'], 'model' => $model, 'generated_at' => now()->toIso8601String()];
        Cache::put($key, $brief, now()->addHours(config('marketing.insights.brief_cache_hours', 6)));

        return ['available' => true] + $brief;
    }

    private function key(string $source): string
    {
        return "marketing:insights:$source:{$this->from->toDateString()}:{$this->to->toDateString()}";
    }

    private function signupSources(): array
    {
        return DB::table('student_profiles')
            ->whereBetween('created_at', [$this->from->startOfDay(), $this->to->endOfDay()])
            ->selectRaw("COALESCE(NULLIF(utm_source, ''), NULLIF(signup_source, ''), 'untracked') as source, count(*) as students")
            ->groupBy('source')->orderByDesc('students')->limit(10)->get()
            ->map(fn ($r) => ['source' => $r->source, 'students' => (int) $r->students])->all();
    }

    private function crossInsights(array $data, array $signups): array
    {
        $out = [];
        $fb = $data['facebook'];
        $ga = $data['analytics'];
        $gsc = $data['search'];

        if ($fb && $ga) {
            $fbSessions = collect($ga['sources'])->filter(fn ($s) => preg_match('/facebook|fb\b|\bm\.facebook|l\.facebook/i', $s['source']))->sum('sessions');
            $engagement = $fb['kpis']['engagement']['value'];
            if ($engagement >= 100 && $fbSessions < $engagement * 0.1) {
                $out[] = Insight::make('cross', 'opportunity',
                    'Facebook engagement is not reaching the website',
                    Insight::int($engagement).' interactions on Facebook but only '.Insight::int($fbSessions).' website sessions from Facebook.',
                    'Put a short link to a specific free quiz in the first line of posts (and in the first comment), and pin a "Start free mock test" post.',
                    75);
            }
        }

        if ($gsc && $fb) {
            $posted = collect($fb['by_topic'])->pluck('posts', 'key');
            $demand = collect($gsc['topics'])->where('key', '!=', 'other')->sortByDesc('impressions')->take(3)
                ->filter(fn ($t) => ($posted[$t['key']] ?? 0) === 0);
            if ($demand->isNotEmpty()) {
                $out[] = Insight::make('cross', 'opportunity',
                    'High search demand, no Facebook posts: '.$demand->pluck('label')->implode(', '),
                    'These are among the most searched topics on Google, but the page did not post about them in this period.',
                    'Add them to the posting calendar — MCQs, syllabus and notice posts for these exams.',
                    65);
            }
        }

        $total = array_sum(array_column($signups, 'students'));
        $untracked = collect($signups)->firstWhere('source', 'untracked')['students'] ?? 0;
        if ($total >= 20 && Insight::share($untracked, $total) >= 80) {
            $out[] = Insight::make('cross', 'warning',
                Insight::pct(Insight::share($untracked, $total)).' of new sign-ups have no source',
                'We cannot tell whether Facebook, Google or word of mouth brings students who register.',
                'Use UTM links everywhere (e.g. ?utm_source=facebook&utm_medium=post&utm_campaign=nursing-mcq) and mark sign-up as a key event in GA4.',
                55);
        }

        return $out;
    }

    /** A compact view of every source for the AI (no raw series). */
    private function digest(): array
    {
        $pick = fn (?array $d, array $keys, int $limit = 10) => $d ? collect($keys)->mapWithKeys(
            fn ($k) => [$k => is_iterable($d[$k] ?? null) ? collect($d[$k])->take($limit)->values()->all() : ($d[$k] ?? null)]
        )->all() : null;

        $sources = array_filter([
            'google_search_console' => $pick(Cache::get($this->key('search'))['data'] ?? null,
                ['kpis', 'brand', 'top_queries', 'striking_distance', 'low_ctr', 'rising', 'declining', 'topics', 'intents', 'pages', 'devices', 'countries'], 15),
            'google_analytics' => $pick(Cache::get($this->key('analytics'))['data'] ?? null,
                ['kpis', 'channels', 'sources', 'landing_pages', 'cities', 'devices', 'events'], 15),
            'facebook_page' => $pick(Cache::get($this->key('facebook'))['data'] ?? null,
                ['page', 'kpis', 'posts_per_week', 'longest_gap_days', 'by_format', 'by_topic', 'by_day', 'top_posts', 'weakest_posts'], 10),
        ]);

        return [
            'period' => ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()],
            'sources' => $sources,
            'signups' => $this->signupSources(),
            'rule_based_findings' => collect($this->summary()['insights'])->take(15)
                ->map(fn ($i) => "[{$i['kind']}] {$i['title']}: {$i['detail']}")->all(),
        ];
    }

    private function briefPrompt(): string
    {
        $exams = DB::table('exam_types')->where('is_active', 1)->pluck('name')->implode('; ');

        return <<<PROMPT
You are a senior growth marketer advising ExamsNepal (examsnepal.com + Android app), an online exam-preparation platform in Nepal.
Students practise free quizzes, Sprint quizzes and full mock tests, and pay for 1/3/6/12-month subscriptions (eSewa, ConnectIPS).
Active exam categories: {$exams}.
The audience is students and job-seekers in Nepal preparing for license, Loksewa (public service) and entrance exams; most are on mobile, budgets are small, and exam calendars (notices, results) drive demand spikes.

You get a JSON digest of Google Search Console, Google Analytics 4 and Facebook Page data for one period, each compared with the previous period, plus rule-based findings.
Write a practical marketing brief for the founder:
- Ground every claim in the numbers given; quote them. Never invent data. If a source is missing, say what it would add instead of guessing.
- Prioritise by expected impact on new registrations and paid subscriptions, with low-cost actions a small team can do this week.
- Be specific to Nepal and to the exams in the data (name the queries, topics, pages, post formats).
- Content ideas must be concrete post or page titles, not categories.
- Plain English, short sentences.
PROMPT;
    }

    private const BRIEF_SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['headline', 'situation', 'priorities', 'content_ideas', 'weekly_plan', 'watch_metrics'],
        'properties' => [
            'headline' => ['type' => 'string', 'description' => 'One sentence: the most important thing to do now.'],
            'situation' => ['type' => 'string', 'description' => '3-5 sentences on market demand and social presence, with numbers.'],
            'priorities' => [
                'type' => 'array',
                'description' => '3-5 priorities, most impactful first.',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['title', 'why', 'actions', 'channel', 'impact', 'effort'],
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'why' => ['type' => 'string', 'description' => 'The evidence from the data.'],
                        'actions' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'channel' => ['type' => 'string', 'enum' => ['seo', 'facebook', 'website', 'email', 'ads', 'content', 'partnerships']],
                        'impact' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                        'effort' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                    ],
                ],
            ],
            'content_ideas' => [
                'type' => 'array',
                'description' => '5-8 ready-to-make posts or pages.',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['title', 'format', 'channel', 'reason'],
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'format' => ['type' => 'string', 'description' => 'e.g. reel, carousel, MCQ image, blog post, landing page'],
                        'channel' => ['type' => 'string'],
                        'reason' => ['type' => 'string'],
                    ],
                ],
            ],
            'weekly_plan' => [
                'type' => 'array',
                'description' => 'One task per day, Sunday to Saturday (Nepal work week starts Sunday).',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['day', 'task'],
                    'properties' => ['day' => ['type' => 'string'], 'task' => ['type' => 'string']],
                ],
            ],
            'watch_metrics' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => '3-5 numbers to check next week.'],
        ],
    ];

    private function setup(string $source): array
    {
        $email = GoogleApi::serviceAccountEmail();
        $google = GoogleApi::hasCredentials()
            ? "Google service account key found ($email)."
            : 'Create a Google Cloud service account, enable the "Google Analytics Data API" and "Google Search Console API", download its JSON key and save it on the server at '.config('marketing.insights.google_credentials').' (or set GOOGLE_INSIGHTS_CREDENTIALS).';

        return match ($source) {
            'search' => [
                $google,
                'In Search Console > Settings > Users and permissions, add '.($email ?? 'the service account email').' with Restricted permission.',
                'Set SEARCH_CONSOLE_SITE in .env: "sc-domain:examsnepal.com" for a domain property, or the exact URL-prefix property (e.g. https://www.examsnepal.com/).',
            ],
            'analytics' => [
                $google,
                'In GA4 > Admin > Property access management, add '.($email ?? 'the service account email').' as Viewer.',
                'Set GA4_PROPERTY_ID in .env to the numeric property id (Admin > Property details), not the G-XXXX measurement id.',
            ],
            'facebook' => [
                'Create a Meta app (developers.facebook.com), add yourself as admin of the ExamsNepal Page.',
                'In Graph API Explorer, get a User token with pages_show_list, pages_read_engagement and read_insights, exchange it for a long-lived token, then call /me/accounts to get the Page\'s long-lived access token.',
                'Set FACEBOOK_PAGE_ID and FACEBOOK_PAGE_TOKEN in .env (FACEBOOK_GRAPH_VERSION optional).',
            ],
        };
    }
}
