<?php

/*
 * Marketing funnel / lifecycle automation (see docs/marketing.md).
 */
return [
    // Timezone used for "days", streaks and send windows.
    'timezone' => 'Asia/Kathmandu',

    // Metric refresh jobs run here. On the VPS a supervisor-managed worker
    // must listen on this queue: `php artisan queue:work --queue=marketing`.
    'queue_connection' => env('MARKETING_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
    'queue' => env('MARKETING_QUEUE', 'marketing'),

    // An attempt started but not submitted after this many hours is logged as
    // exam_abandoned.
    'abandon_after_hours' => 3,

    // Lifecycle/marketing mail goes out through the VPS's own Exim (the
    // default mailer). cPanel caps outgoing mail per domain per hour, so the
    // automation engine throttles below that cap.
    'mail' => [
        'from_address' => env('MARKETING_MAIL_FROM_ADDRESS', 'info@examsnepal.com'),
        'from_name' => env('MARKETING_MAIL_FROM_NAME', 'ExamsNepal'),
        'max_per_hour' => (int) env('MARKETING_MAIL_MAX_PER_HOUR', 200),
        // Postal address shown in every marketing footer (anti-spam best practice).
        'postal_address' => env('MARKETING_POSTAL_ADDRESS', 'ExamsNepal, Kathmandu, Nepal'),
    ],

    // Links in emails point here (the student web app). Paths are appended.
    'site_url' => rtrim(env('MARKETING_SITE_URL', 'https://www.examsnepal.com'), '/'),

    // Frequency caps for non-transactional messages (lifecycle + promotional), per channel.
    'caps' => [
        'email' => ['min_hours_between' => 48, 'max_per_7_days' => 3], // the spec's guardrail
        'push' => ['min_hours_between' => 20, 'max_per_7_days' => 5],  // allows daily-ish streak nudges
        'sms' => ['min_hours_between' => 72, 'max_per_7_days' => 2],   // expensive, high-value only
    ],

    // Non-transactional mail only goes out inside these Asia/Kathmandu windows.
    'send_windows' => [['07:00', '09:00'], ['18:00', '20:30']],

    // A queued message not sendable within this long after it was due is dropped.
    'stale_after_hours' => 72,

    // Broadcasts compete with automations at this priority (automations: 0-1000, higher wins).
    'broadcast_priority' => 50,

    // Goal event within this many hours of a send counts as a conversion.
    'attribution_hours' => 72,

    // Soft bounces within 30 days before an address is suppressed.
    'soft_bounce_limit' => 3,

    // Push notifications go through the app's FCMService (channel "push"/"auto").
    'push_enabled' => (bool) env('MARKETING_PUSH_ENABLED', true),

    // SMS for high-value automations only (automations.allow_sms). Driver: log | sparrow.
    'sms' => [
        'driver' => env('MARKETING_SMS_DRIVER', 'log'),
        'sparrow_token' => env('SPARROW_SMS_TOKEN'),
        'sparrow_from' => env('SPARROW_SMS_FROM', 'ExamsNepal'),
    ],

    // In-app banner on the student dashboard, chosen by lifecycle stage (no banner for unlisted stages).
    'banners' => [
        'new' => ['tone' => 'info', 'title' => 'Start with a free quiz', 'body' => 'Ten minutes shows you exactly where you stand.', 'cta_label' => 'Take a free quiz', 'cta_path' => '/student/exams/free-quiz'],
        'registered_inactive' => ['tone' => 'info', 'title' => 'Your first quiz is waiting', 'body' => "You don't need a subscription to start.", 'cta_label' => 'Take a free quiz', 'cta_path' => '/student/exams/free-quiz'],
        'activated' => ['tone' => 'info', 'title' => 'Try a Sprint next', 'body' => 'A 15-minute quiz on one topic, with every answer explained.', 'cta_label' => 'Start a Sprint', 'cta_path' => '/student/exams/sprint-quiz'],
        'free_only' => ['tone' => 'promo', 'title' => 'Ready for a full mock test?', 'body' => 'See how you do under real exam timing.', 'cta_label' => 'Try a mock test', 'cta_path' => '/student/exams/mock-tests'],
        'engaged_free' => ['tone' => 'promo', 'title' => 'Unlock every Sprint and Mock', 'body' => 'The 3-month plan covers a full revision cycle.', 'cta_label' => 'See plans', 'cta_path' => '/student/subscription'],
        'hot_lead' => ['tone' => 'promo', 'title' => 'Finish upgrading', 'body' => 'Your plan is one step away. Need help paying? Contact us.', 'cta_label' => 'Complete payment', 'cta_path' => '/student/subscription'],
        'expiring_soon' => ['tone' => 'warning', 'title' => 'Your plan ends soon', 'body' => 'Renew now to keep your Sprints and Mocks without a gap.', 'cta_label' => 'Renew', 'cta_path' => '/student/subscription'],
        'expired' => ['tone' => 'warning', 'title' => 'Your mock tests are locked', 'body' => 'Renew to pick up where you left off.', 'cta_label' => 'Renew', 'cta_path' => '/student/subscription'],
        'paid_inactive' => ['tone' => 'info', 'title' => 'Keep your streak going', 'body' => 'One mock test this week keeps you on track.', 'cta_label' => 'Take a mock', 'cta_path' => '/student/exams/mock-tests'],
        'dormant' => ['tone' => 'info', 'title' => 'Welcome back!', 'body' => "We've added new practice since your last visit.", 'cta_label' => 'Take a free quiz', 'cta_path' => '/student/exams/free-quiz'],
    ],

    // Web & social insights page (docs/marketing.md#web--social-insights).
    // Google: one service account JSON key, added as a Viewer on the GA4
    // property and as a (restricted) user on the Search Console property.
    'insights' => [
        'cache_minutes' => (int) env('MARKETING_INSIGHTS_CACHE_MINUTES', 60),
        'timezone' => 'Asia/Kathmandu',
        'google_credentials' => env('GOOGLE_INSIGHTS_CREDENTIALS', storage_path('app/private/google-insights.json')),
        // Numeric GA4 property id (Admin > Property details), not the G-XXXX measurement id.
        'ga4_property_id' => env('GA4_PROPERTY_ID'),
        // "sc-domain:examsnepal.com" for a domain property, or the exact URL-prefix property ("https://www.examsnepal.com/").
        'search_console_site' => env('SEARCH_CONSOLE_SITE'),
        'facebook' => [
            'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v23.0'),
            'page_id' => env('FACEBOOK_PAGE_ID'),
            // Long-lived Page access token (pages_read_engagement, read_insights).
            'page_token' => env('FACEBOOK_PAGE_TOKEN'),
            // Page Insights metrics, fetched one by one; Meta renames these
            // often, so any the API rejects are skipped and listed as unavailable.
            'page_metrics' => [
                'page_daily_follows_unique' => 'New followers',
                'page_daily_unfollows_unique' => 'Unfollows',
                'page_media_view' => 'Views',
                'page_post_engagements' => 'Post engagements',
            ],
            'max_posts' => 300,
        ],
        // Model for the AI marketing brief; empty = the notices model. Uses
        // the notices AI provider/key (NOTICES_AI_PROVIDER), so that must be set.
        'ai_model' => env('MARKETING_INSIGHTS_AI_MODEL'),
        'brief_cache_hours' => 6,
        // Queries containing these are "brand" searches (people who already know us).
        'brand_terms' => ['examsnepal', 'exams nepal', 'exam nepal', 'examnepal', 'examsnp'],
        // Search queries and Facebook posts are bucketed by these keywords
        // (lowercase, first match wins; keywords of 3 letters or fewer match whole
        // words only, longer ones match word starts). exam_type_ids ties a topic to
        // student_profiles.exam_type_id to compare search demand with our student base.
        'topics' => [
            'dental' => ['label' => 'Dental', 'exam_type_ids' => [9], 'keywords' => ['dental', 'bds', 'mds', 'dentist']],
            'nursing' => ['label' => 'Nursing', 'exam_type_ids' => [5], 'keywords' => ['nursing', 'nurse', 'bns', 'anm', 'pcl', 'bsc nursing', 'midwi']],
            'radiography' => ['label' => 'Radiography', 'exam_type_ids' => [10], 'keywords' => ['radiograph', 'radiology', 'x-ray', 'xray', 'imaging']],
            'pharmacy' => ['label' => 'Pharmacy', 'exam_type_ids' => [11], 'keywords' => ['pharma', 'pharmacy', 'pharmacist', 'drug']],
            'medical' => ['label' => 'Medical (NMCLE / MD-MS)', 'exam_type_ids' => [1, 3], 'keywords' => ['nmcle', 'mbbs', 'medical', 'mdms', 'md ms', 'md/ms', 'cee', 'nmc', 'doctor', 'health assistant', 'ha']],
            'computer' => ['label' => 'Computer engineering', 'exam_type_ids' => [12], 'keywords' => ['computer', 'it officer', 'programming', 'software']],
            'civil' => ['label' => 'Civil engineering', 'exam_type_ids' => [13], 'keywords' => ['civil', 'sub engineer', 'sub-engineer', 'overseer']],
            'electrical' => ['label' => 'Electrical engineering', 'exam_type_ids' => [14], 'keywords' => ['electrical', 'electronics']],
            'engineering' => ['label' => 'Engineering (general)', 'exam_type_ids' => [2, 6], 'keywords' => ['engineering', 'nec', 'ioe', 'engineer']],
            'loksewa' => ['label' => 'Loksewa (general)', 'exam_type_ids' => [4], 'keywords' => ['loksewa', 'lok sewa', 'psc', 'लोकसेवा', 'kharidar', 'nayab subba', 'section officer']],
            'agriculture' => ['label' => 'Agriculture', 'exam_type_ids' => [7], 'keywords' => ['agri', 'jta', 'veterinary', 'vet']],
        ],
        // What the searcher wants, independent of exam.
        'intents' => [
            'practice' => ['label' => 'Practice / MCQ', 'keywords' => ['mcq', 'mock', 'model question', 'question bank', 'practice', 'quiz', 'test', 'sample question', 'objective']],
            'past_papers' => ['label' => 'Old / past questions', 'keywords' => ['old question', 'past question', 'past paper', 'previous year', 'question paper', 'old paper', 'collection']],
            'syllabus' => ['label' => 'Syllabus / pattern', 'keywords' => ['syllabus', 'pattern', 'curriculum', 'course']],
            'notice' => ['label' => 'Notices / results / dates', 'keywords' => ['result', 'notice', 'vacancy', 'date', 'schedule', 'admit card', 'form', 'application', 'routine']],
            'license' => ['label' => 'License exam', 'keywords' => ['license', 'licence', 'licensing']],
            'entrance' => ['label' => 'Entrance exam', 'keywords' => ['entrance']],
        ],
    ],

    // A repeat pricing_viewed within this window is not logged again (the plan
    // list endpoint is also hit by the home page).
    'pricing_view_dedupe_minutes' => 30,
];
