<?php

namespace App\Services\Marketing;

use App\Models\Marketing\Automation;

/**
 * The starter set of lifecycle emails (docs/marketing.md, Phase 5), installed
 * by `php artisan marketing:install-starter` - always switched OFF so each one
 * can be dry-run and test-sent before it goes live.
 *
 * Priorities (higher wins when several are due): payment help > checkout
 * recovery > onboarding > renewal > activation > upgrade nudges >
 * performance > win-back. Frequency caps (1 per 48h, 3 per 7 days) apply on
 * top, which is why follow-ups are spaced at least 48h apart.
 */
class StarterCatalog
{
    private const FREE = 'FREE_QUIZ';
    private const SPRINT = 'SPRINT_QUIZ';
    private const MOCK = 'MOCK_TEST';

    /** @return array<string, array> keyed by template key */
    public static function templates(): array
    {
        $p = fn (string ...$paragraphs) => implode('', array_map(fn ($x) => "<p>{$x}</p>", $paragraphs));

        return [
            // ---------------------------------------------------------------- A. Activation
            'welcome' => [
                'name' => 'Welcome',
                'subject' => 'Welcome to ExamsNepal, {{first_name}}',
                'preheader' => 'Start with a free {{target_exam}} quiz. It takes 10 minutes.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    'Welcome to ExamsNepal! The fastest way to see where you stand for <strong>{{target_exam}}</strong> is a free quiz. It takes about 10 minutes and shows your score and every answer explained.',
                    'No subscription needed to start.',
                ),
                'cta_label' => 'Take a free quiz',
                'cta_path' => '/student/exams/free-quiz',
            ],
            'activate_1' => [
                'name' => 'Activation 1: no exam after a day',
                'subject' => 'Your first {{target_exam}} quiz is waiting',
                'preheader' => "You don't need a subscription to start.",
                'html_body' => $p(
                    'Hi {{first_name}},',
                    "You signed up yesterday but haven't taken an exam yet. You don't need a subscription to start: this week's free quiz is open now.",
                    'Ten minutes today tells you exactly which topics to focus on.',
                ),
                'cta_label' => 'Start the free quiz',
                'cta_path' => '/student/exams/free-quiz',
            ],
            'activate_2' => [
                'name' => 'Activation 2: social proof',
                'subject' => '{{students_active_this_week}} {{target_exam}} aspirants practised this week',
                'preheader' => 'Join them with one free quiz.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    '<strong>{{students_active_this_week}} students</strong> preparing for {{target_exam}} took an exam on ExamsNepal this week.',
                    'Your free quiz is still waiting. Start today and you will know your score before dinner.',
                ),
                'cta_label' => 'Take my free quiz',
                'cta_path' => '/student/exams/free-quiz',
            ],
            'activate_3' => [
                'name' => 'Activation 3: last nudge',
                'subject' => 'Still preparing for {{target_exam}}?',
                'preheader' => 'One free quiz, or pick a different exam.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    "This is our last reminder: a free {{target_exam}} quiz is ready whenever you are.",
                    'Not preparing for {{target_exam}} anymore? <a href="{{profile_url}}">Pick a different exam</a> and we will send you the right practice instead.',
                ),
                'cta_label' => 'Take a free quiz',
                'cta_path' => '/student/exams/free-quiz',
            ],

            // ---------------------------------------------------------------- B. Free -> Mock / Sprint
            'free_to_mock_1' => [
                'name' => 'Free to Mock 1: after a free quiz',
                'subject' => 'You scored {{last_score}}%. Try it under real exam conditions',
                'preheader' => 'Full mock tests show exactly where you stand.',
                'html_body' => $p(
                    'Nice work, {{first_name}}! You scored <strong>{{last_score}}%</strong> on your free quiz.',
                    "Free quizzes come once a week, but your exam won't wait. Full-length mock tests time you like the real {{target_exam}} exam and show exactly where you stand.",
                ),
                'cta_label' => 'Try a mock test',
                'cta_path' => '/student/exams/mock-tests',
            ],
            'free_to_mock_2' => [
                'name' => 'Free to Mock 2: a week later',
                'subject' => 'Sprints: 15 minutes, one topic, instant score',
                'preheader' => 'The quickest way to fix weak topics.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    "You've taken {{total_attempts}} free quizzes. Great habit! The students who improve fastest add short Sprint quizzes: one topic, about 15 minutes, answers explained.",
                    'Try one today and compare it with your {{avg_score}}% average.',
                ),
                'cta_label' => 'Start a Sprint',
                'cta_path' => '/student/exams/sprint-quiz',
            ],
            'free_waiting' => [
                'name' => 'Free quiz done: keep momentum',
                'category' => 'promotional',
                'subject' => "Don't lose your momentum, {{first_name}}",
                'preheader' => 'Keep practising until the next free quiz.',
                'html_body' => $p(
                    "You've finished this week's free quiz. Well done!",
                    "Rather than waiting a week, keep practising with Sprints and full Mock tests. Most students choose the <strong>3-month plan</strong>: it covers a full revision cycle for {{target_exam}}.",
                ),
                'cta_label' => 'See plans',
                'cta_path' => '/student/subscription',
            ],

            // ---------------------------------------------------------------- D. Intent and conversion
            'checkout_abandoned_1' => [
                'name' => 'Checkout help (1 hour)',
                'subject' => 'Did your payment go through?',
                'preheader' => 'If eSewa or ConnectIPS had a problem, you can retry in a minute.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    "It looks like your ExamsNepal payment didn't complete. That usually happens when the eSewa or bank page times out.",
                    'You can retry in under a minute. If money left your account but your plan is not active, just reply to this email and we will sort it out.',
                ),
                'cta_label' => 'Retry payment',
                'cta_path' => '/student/subscription',
            ],
            'checkout_abandoned_2' => [
                'name' => 'Checkout reminder (2 days)',
                'subject' => 'Your {{target_exam}} plan is one step away',
                'preheader' => 'Unlock every Sprint and Mock test.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    'You were one step away from unlocking every Sprint and Mock test for {{target_exam}}.',
                    'Need help choosing a plan or paying? Reply to this email and we will help.',
                ),
                'cta_label' => 'Finish upgrading',
                'cta_path' => '/student/subscription',
            ],
            'pricing_viewed' => [
                'name' => 'Viewed pricing twice',
                'category' => 'promotional',
                'subject' => 'Which ExamsNepal plan fits you?',
                'preheader' => 'A quick guide to picking a plan.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    'Saw you looking at our plans. A quick guide:',
                    '<strong>1 month</strong>: a final push just before the exam.<br><strong>3 months</strong> (most popular): a full revision cycle with weekly mocks.<br><strong>6 months</strong>: best value if your exam is further away.',
                    'Every plan unlocks all Sprints and Mock tests for {{target_exam}}.',
                ),
                'cta_label' => 'Compare plans',
                'cta_path' => '/student/subscription',
            ],
            'payment_failed' => [
                'name' => 'Payment failed',
                'category' => 'transactional',
                'subject' => 'Your payment did not go through',
                'preheader' => 'No money was taken for your plan. You can try again.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    'Your recent payment to ExamsNepal failed, so your plan was not activated.',
                    'You can try again with eSewa or ConnectIPS. If money was deducted from your account, reply to this email with the transaction details and we will fix it.',
                ),
                'cta_label' => 'Try again',
                'cta_path' => '/student/subscription',
            ],

            // ---------------------------------------------------------------- E. Paid users
            'paid_welcome' => [
                'name' => 'Paid welcome',
                'subject' => "You're in, {{first_name}}. Start with a full mock",
                'preheader' => 'Your plan is active until {{subscription_ends_at}}.',
                'html_body' => $p(
                    'Thanks for upgrading, {{first_name}}! Your plan is active until <strong>{{subscription_ends_at}}</strong>.',
                    'The best first step: take one full Mock test. It gives you a baseline score, so every week after this you can see your progress.',
                ),
                'cta_label' => 'Take my first mock',
                'cta_path' => '/student/exams/mock-tests',
            ],
            'paid_inactive' => [
                'name' => 'Paid but inactive',
                'subject' => 'Your {{target_exam}} practice is waiting',
                'preheader' => 'One exam this week keeps you on track.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    "You haven't taken an exam in a week. Your plan is still active until {{subscription_ends_at}}.",
                    'Suggested next: <strong>{{recommended_exam}}</strong>.',
                ),
                'cta_label' => 'Continue practising',
                'cta_path' => '/student/exams/mock-tests',
            ],
            'expiring_7d' => [
                'name' => 'Subscription ends in 7 days',
                'subject' => 'Your plan ends on {{subscription_ends_at}}',
                'preheader' => "Here's what you achieved so far.",
                'html_body' => $p(
                    'Hi {{first_name}},',
                    'Your ExamsNepal plan ends on <strong>{{subscription_ends_at}}</strong>. So far you have taken <strong>{{total_attempts}} exams</strong>, averaging {{avg_score}}% with a best of {{best_score}}%.',
                    'Renew now to keep your momentum going until {{target_exam}}.',
                ),
                'cta_label' => 'Renew my plan',
                'cta_path' => '/student/subscription',
            ],
            'expiring_1d' => [
                'name' => 'Subscription ends tomorrow',
                'subject' => 'Your ExamsNepal plan ends tomorrow',
                'preheader' => 'Renew today to keep your Sprints and Mocks.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    'Your plan ends tomorrow ({{subscription_ends_at}}). After that, Sprints and Mock tests lock again.',
                    'Renew today and keep your practice going without a gap.',
                ),
                'cta_label' => 'Renew now',
                'cta_path' => '/student/subscription',
            ],
            'expired_winback' => [
                'name' => 'Expired: win-back (3 days)',
                'category' => 'promotional',
                'subject' => 'Your mock tests are locked, {{first_name}}',
                'preheader' => 'Pick up where you left off.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    'Your ExamsNepal plan ended a few days ago. You took {{total_attempts}} exams with a best score of {{best_score}}%. Don\'t let that progress fade.',
                    'Renew to unlock every Sprint and Mock test for {{target_exam}} again.',
                ),
                'cta_label' => 'Renew my plan',
                'cta_path' => '/student/subscription',
            ],
            'expired_winback_2' => [
                'name' => 'Expired: win-back (14 days)',
                'category' => 'promotional',
                'subject' => 'New practice for {{target_exam}} since you left',
                'preheader' => 'Come back to Sprints and Mocks.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    "It's been two weeks since your plan ended. We've kept adding Sprints and Mock tests for {{target_exam}}.",
                    'Come back for a month, or pick a longer plan for the full run-up to your exam.',
                ),
                'cta_label' => 'See plans',
                'cta_path' => '/student/subscription',
            ],

            // ---------------------------------------------------------------- C. Performance
            'score_improving' => [
                'name' => 'Score improving',
                'subject' => "You're improving, {{first_name}}!",
                'preheader' => 'Your recent scores are going up. Keep the streak.',
                'html_body' => $p(
                    'Great progress, {{first_name}}! Your last few scores are clearly higher than before. Your latest was <strong>{{last_score}}%</strong>.',
                    'Keep the streak going with one more exam this week.',
                ),
                'cta_label' => 'Keep going',
                'cta_path' => '/student/dashboard',
            ],
            'score_declining' => [
                'name' => 'Score declining (supportive)',
                'subject' => 'A dip is normal. Here is how to bounce back',
                'preheader' => 'Short, focused practice works best.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    'Your last few scores dipped a little. That is completely normal during preparation, especially as the topics get harder.',
                    'What helps most: short Sprints on one topic at a time, then read every explanation. Your average is still {{avg_score}}%.',
                ),
                'cta_label' => 'Practise with a Sprint',
                'cta_path' => '/student/exams/sprint-quiz',
            ],
            'weak_subject' => [
                'name' => 'Weak subject',
                'subject' => '{{weakest_subject}} is pulling your score down',
                'preheader' => 'A focused set to fix it.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    '<strong>{{weakest_subject}}</strong> is your weakest subject right now ({{weakest_subject_score}}%). A few focused sessions there will lift your overall score fastest.',
                ),
                'cta_label' => 'Practise {{weakest_subject}}',
                'cta_path' => '/student/exams/sprint-quiz',
            ],
            'high_performer' => [
                'name' => 'High performer',
                'category' => 'promotional',
                'subject' => "You're averaging {{avg_score}}%. Ready for a full mock?",
                'preheader' => 'See how you do under real exam conditions.',
                'html_body' => $p(
                    'Impressive, {{first_name}}! You are averaging <strong>{{avg_score}}%</strong>, well ahead of most {{target_exam}} aspirants.',
                    'The next step is a full-length Mock test under real exam timing. It is the closest thing to exam day.',
                ),
                'cta_label' => 'Try a full mock',
                'cta_path' => '/student/exams/mock-tests',
            ],
            'low_performer' => [
                'name' => 'Low performer (encouraging)',
                'subject' => 'Every attempt makes you better, {{first_name}}',
                'preheader' => 'A simple plan for this week.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    "You've taken {{total_attempts}} exams, and that practice is exactly what builds your score. Here is a simple plan for this week:",
                    '1. Take one short Sprint.<br>2. Read every explanation, even the ones you got right.<br>3. Retry the same topic two days later.',
                ),
                'cta_label' => 'Start a Sprint',
                'cta_path' => '/student/exams/sprint-quiz',
            ],

            // ---------------------------------------------------------------- F. Recurring
            'weekly_progress_report' => [
                'name' => 'Weekly progress report',
                'subject' => 'Your ExamsNepal week: {{attempts_this_week}} exams',
                'preheader' => 'Your ExamsNepal progress report.',
                'html_body' => '<p>Hi {{first_name}}, here is your week on ExamsNepal.</p>'
                    . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:16px 0;border-collapse:separate;border-spacing:6px">'
                    . '<tr>'
                    . '<td style="background:#f1f6f2;border-radius:8px;padding:12px;text-align:center"><div style="font-size:22px;font-weight:700">{{attempts_this_week}}</div><div style="font-size:12px;color:#5f665f">exams this week</div></td>'
                    . '<td style="background:#f1f6f2;border-radius:8px;padding:12px;text-align:center"><div style="font-size:22px;font-weight:700">{{avg_score_this_week}}</div><div style="font-size:12px;color:#5f665f">average (last week {{avg_score_last_week}})</div></td>'
                    . '</tr><tr>'
                    . '<td style="background:#f1f6f2;border-radius:8px;padding:12px;text-align:center"><div style="font-size:22px;font-weight:700">{{best_score}}%</div><div style="font-size:12px;color:#5f665f">best score ever</div></td>'
                    . '<td style="background:#f1f6f2;border-radius:8px;padding:12px;text-align:center"><div style="font-size:22px;font-weight:700">{{streak}}</div><div style="font-size:12px;color:#5f665f">day streak</div></td>'
                    . '</tr></table>'
                    . '<p><strong>Recommended next:</strong> {{recommended_exam}}</p>',
                'cta_label' => 'Take my next exam',
                'cta_path' => '/student/dashboard',
            ],
            'exam_countdown' => [
                'name' => 'Exam countdown',
                'subject' => '{{days_to_exam}} days to {{target_exam}}. Your plan',
                'preheader' => 'How to use the days you have left.',
                'html_body' => $p(
                    'Hi {{first_name}}, your exam is <strong>{{days_to_exam}} days</strong> away.',
                    'A simple plan for the time left: one full Mock test every week, Sprints on your weakest topics in between, and review every wrong answer within a day.',
                ),
                'cta_label' => 'Take a mock test',
                'cta_path' => '/student/exams/mock-tests',
            ],
            'streak_reminder' => [
                'name' => 'Streak reminder (push)',
                'subject' => 'Keep your {{streak}}-day streak alive',
                'preheader' => 'One quick quiz today keeps it going.',
                'html_body' => $p('Hi {{first_name}}, you have practised {{streak}} days in a row. One quick quiz today keeps the streak alive.'),
                'cta_label' => 'Practise now',
                'cta_path' => '/student/exams/sprint-quiz',
            ],
            'dormant_winback' => [
                'name' => 'Dormant win-back',
                'subject' => "We've added new {{target_exam}} practice",
                'preheader' => 'Pick up where you left off.',
                'html_body' => $p(
                    'Hi {{first_name}},',
                    "It's been a while since your last exam. Since then we've added new quizzes, Sprints and Mock tests for {{target_exam}}.",
                    'Jump back in with this week\'s free quiz. Your best score so far is {{best_score}}%.',
                ),
                'cta_label' => 'Take a free quiz',
                'cta_path' => '/student/exams/free-quiz',
            ],
        ];
    }

    /** @return array<string, array> keyed by automation key */
    public static function automations(): array
    {
        $event = fn (string $event, array $a) => $a + ['trigger_type' => Automation::TRIGGER_EVENT, 'trigger_event' => $event];
        $scheduled = fn (array $a) => $a + ['trigger_type' => Automation::TRIGGER_SCHEDULED];
        $never = ['subscription_status' => 'never'];

        $countdown = [];
        foreach ([60 => 58, 30 => 57, 14 => 56, 7 => 55] as $days => $priority) {
            $countdown["exam_countdown_{$days}"] = $scheduled([
                'name' => "Exam countdown: {$days} days",
                'template_key' => 'exam_countdown',
                'conditions' => ['days_to_exam_min' => $days - 1, 'days_to_exam_max' => $days],
                'goal_event' => 'exam_submitted', 'goal_properties' => ['exam_type' => self::MOCK],
                'cooldown_days' => 5, 'priority' => $priority,
            ]);
        }

        return [
            // A. Activation. Welcome fires on first login (after email verification,
            // or right away for Google sign-in), not at signup.
            'welcome' => $event('logged_in', [
                'name' => 'Welcome', 'template_key' => 'welcome',
                'conditions' => ['attempts_max' => 0, 'signed_up_hours_max' => 72],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 3650, 'priority' => 94,
            ]),
            'activate_1' => $scheduled([
                'name' => 'Activation 1 (day 1)', 'template_key' => 'activate_1',
                'conditions' => ['attempts_max' => 0, 'signed_up_hours_min' => 24, 'signed_up_hours_max' => 72],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 3650, 'priority' => 80,
            ]),
            'activate_2' => $scheduled([
                'name' => 'Activation 2 (day 3)', 'template_key' => 'activate_2',
                'conditions' => ['attempts_max' => 0, 'signed_up_hours_min' => 72, 'signed_up_hours_max' => 168],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 3650, 'priority' => 79,
            ]),
            'activate_3' => $scheduled([
                'name' => 'Activation 3 (day 7)', 'template_key' => 'activate_3',
                'conditions' => ['attempts_max' => 0, 'signed_up_hours_min' => 168, 'signed_up_hours_max' => 264],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 3650, 'priority' => 78,
            ]),

            // B. Free -> Mock / Sprint
            'free_to_mock_1' => $event('exam_submitted', [
                'name' => 'Free to Mock 1 (after a free quiz)', 'template_key' => 'free_to_mock_1',
                'trigger_properties' => ['exam_type' => self::FREE],
                'conditions' => ['stage' => 'activated,free_only'],
                'delay_minutes' => 30,
                'goal_event' => 'exam_submitted', 'goal_properties' => ['exam_type' => [self::SPRINT, self::MOCK]],
                'cooldown_days' => 3650, 'priority' => 75,
            ]),
            'free_to_mock_2' => $scheduled([
                'name' => 'Free to Mock 2 (a week later)', 'template_key' => 'free_to_mock_2',
                'conditions' => ['stage' => 'activated,free_only', 'received' => 'free_to_mock_1', 'received_days_min' => 7, 'last_attempt_days_max' => 21],
                'goal_event' => 'exam_submitted', 'goal_properties' => ['exam_type' => [self::SPRINT, self::MOCK]],
                'cooldown_days' => 3650, 'priority' => 68,
            ]),
            'free_waiting' => $event('exam_submitted', [
                'name' => 'Free quiz done, next not yet open', 'template_key' => 'free_waiting',
                'trigger_properties' => ['exam_type' => self::FREE],
                'conditions' => $never + ['received' => 'free_to_mock_1', 'received_days_min' => 2],
                'delay_minutes' => 60,
                'goal_event' => 'payment_succeeded', 'cooldown_days' => 14, 'priority' => 65, 'is_upsell' => true,
            ]),

            // D. Intent and conversion
            // High-value moments: app push if installed, else email, else SMS.
            'checkout_abandoned_1' => $event('checkout_started', [
                'name' => 'Checkout help (after 1 hour)', 'template_key' => 'checkout_abandoned_1',
                'channel' => 'auto', 'allow_sms' => true,
                'delay_minutes' => 60, 'goal_event' => 'payment_succeeded', 'cooldown_days' => 7, 'priority' => 98,
            ]),
            // 48h (not 24h): the frequency cap allows one lifecycle email per 48h.
            'checkout_abandoned_2' => $event('checkout_started', [
                'name' => 'Checkout reminder (after 2 days)', 'template_key' => 'checkout_abandoned_2',
                'delay_minutes' => 48 * 60, 'goal_event' => 'payment_succeeded', 'cooldown_days' => 14, 'priority' => 96,
            ]),
            'pricing_viewed' => $scheduled([
                'name' => 'Viewed pricing 2+ times', 'template_key' => 'pricing_viewed',
                'conditions' => $never + ['pricing_views_min' => 2, 'pricing_viewed_days_max' => 2],
                'goal_event' => 'payment_succeeded', 'cooldown_days' => 30, 'priority' => 72, 'is_upsell' => true,
            ]),
            'payment_failed' => $event('payment_failed', [
                'name' => 'Payment failed', 'template_key' => 'payment_failed',
                'delay_minutes' => 15, 'goal_event' => 'payment_succeeded', 'cooldown_days' => 1, 'priority' => 100,
            ]),

            // E. Paid users
            'paid_welcome' => $event('payment_succeeded', [
                'name' => 'Paid welcome', 'template_key' => 'paid_welcome',
                'goal_event' => 'exam_submitted', 'goal_properties' => ['exam_type' => self::MOCK],
                'cooldown_days' => 25, 'priority' => 95,
            ]),
            'paid_inactive' => $scheduled([
                'name' => 'Paid but inactive 7+ days', 'template_key' => 'paid_inactive',
                'conditions' => ['stage' => 'paid_inactive', 'last_attempt_days_min' => 7],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 14, 'priority' => 60,
            ]),
            'expiring_7d' => $scheduled([
                'name' => 'Subscription ends in 7 days', 'template_key' => 'expiring_7d',
                'conditions' => ['ends_in_days_min' => 6, 'ends_in_days_max' => 7],
                'goal_event' => 'payment_succeeded', 'cooldown_days' => 20, 'priority' => 90,
            ]),
            'expiring_1d' => $scheduled([
                'name' => 'Subscription ends tomorrow', 'template_key' => 'expiring_1d',
                'channel' => 'auto', 'allow_sms' => true,
                'conditions' => ['ends_in_days_min' => 0, 'ends_in_days_max' => 1],
                'goal_event' => 'payment_succeeded', 'cooldown_days' => 5, 'priority' => 92,
            ]),
            'expired_winback' => $scheduled([
                'name' => 'Expired 3 days ago', 'template_key' => 'expired_winback',
                'conditions' => ['expired_days_min' => 3, 'expired_days_max' => 5],
                'goal_event' => 'payment_succeeded', 'cooldown_days' => 30, 'priority' => 85,
            ]),
            'expired_winback_2' => $scheduled([
                'name' => 'Expired 14 days ago', 'template_key' => 'expired_winback_2',
                'conditions' => ['expired_days_min' => 14, 'expired_days_max' => 17],
                'goal_event' => 'payment_succeeded', 'cooldown_days' => 30, 'priority' => 84,
            ]),

            // C. Performance
            'score_improving' => $scheduled([
                'name' => 'Score improving', 'template_key' => 'score_improving',
                'conditions' => ['segment' => 'score_improving', 'last_attempt_days_max' => 3],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 14, 'priority' => 40,
            ]),
            'score_declining' => $scheduled([
                'name' => 'Score declining', 'template_key' => 'score_declining',
                'conditions' => ['segment' => 'score_declining', 'last_attempt_days_max' => 7],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 14, 'priority' => 45,
            ]),
            // Needs exams tagged with subjects (php artisan subjects:infer).
            'weak_subject' => $scheduled([
                'name' => 'Weak subject (below 40%)', 'template_key' => 'weak_subject',
                'conditions' => ['segment' => 'has_weak_subject', 'weakest_score_max' => 40, 'last_attempt_days_max' => 14],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 14, 'priority' => 44,
            ]),
            'high_performer' => $scheduled([
                'name' => 'High performer, not paid', 'template_key' => 'high_performer',
                'conditions' => $never + ['segment' => 'high_performer', 'attempts_min' => 3, 'last_attempt_days_max' => 14],
                'goal_event' => 'exam_submitted', 'goal_properties' => ['exam_type' => self::MOCK],
                'cooldown_days' => 30, 'priority' => 70, 'is_upsell' => true,
            ]),
            'low_performer' => $scheduled([
                'name' => 'Low performer', 'template_key' => 'low_performer',
                'conditions' => ['segment' => 'low_performer', 'attempts_min' => 3, 'last_attempt_days_max' => 14],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 21, 'priority' => 42,
            ]),

            // F. Recurring
            'weekly_progress_report' => $scheduled([
                'name' => 'Weekly progress report (Sunday 7:00)', 'template_key' => 'weekly_progress_report',
                'conditions' => ['last_attempt_days_max' => 14],
                'schedule' => ['weekdays' => [0], 'hours' => [7]],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 6, 'priority' => 55,
            ]),
            ...$countdown,
            // App users only: a daily nudge at 19:00 NPT while a streak is alive but not yet extended today.
            'streak_reminder' => $scheduled([
                'name' => 'Streak reminder (push, 19:00)', 'template_key' => 'streak_reminder', 'channel' => 'push',
                'conditions' => ['segment' => 'streak_active', 'attempted_today' => 0],
                'schedule' => ['hours' => [19]],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 0, 'priority' => 50,
            ]),
            'dormant_winback_21' => $scheduled([
                'name' => 'Dormant 21 days', 'template_key' => 'dormant_winback',
                'conditions' => ['stage' => 'dormant', 'last_attempt_days_min' => 21, 'last_attempt_days_max' => 23],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 20, 'priority' => 35,
            ]),
            'dormant_winback_45' => $scheduled([
                'name' => 'Dormant 45 days', 'template_key' => 'dormant_winback',
                'conditions' => ['stage' => 'dormant', 'last_attempt_days_min' => 45, 'last_attempt_days_max' => 47],
                'goal_event' => 'exam_submitted', 'cooldown_days' => 20, 'priority' => 34,
            ]),
        ];
    }
}
