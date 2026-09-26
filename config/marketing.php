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

    // A repeat pricing_viewed within this window is not logged again (the plan
    // list endpoint is also hit by the home page).
    'pricing_view_dedupe_minutes' => 30,
];
