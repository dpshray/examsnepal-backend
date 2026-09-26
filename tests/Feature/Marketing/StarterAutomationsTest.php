<?php

namespace Tests\Feature\Marketing;

use App\Enums\ExamTypeEnum;
use App\Models\Marketing\Automation;
use App\Models\Marketing\EmailTemplate;
use App\Models\Marketing\MessageSend;
use App\Services\Marketing\AutomationEngine;
use App\Services\Marketing\EventTracker;
use App\Services\Marketing\MarketingSettings;
use App\Services\Marketing\StarterCatalog;
use App\Services\Marketing\StudentFilter;
use App\Services\Marketing\StudentMetricsCalculator;
use App\Services\Marketing\TemplateRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use ReflectionClass;

class StarterAutomationsTest extends MarketingDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 18:30', 'Asia/Kathmandu'));
        $this->artisan('marketing:install-starter')->assertSuccessful();
        MarketingSettings::set(MarketingSettings::PAUSED, false);
    }

    private function enable(string ...$keys): void
    {
        Automation::whereIn('key', $keys)->update(['is_active' => true]);
    }

    /** One scheduler tick: hourly planner + dispatcher, with fresh metrics. */
    private function tick(array $studentIds): void
    {
        (new StudentMetricsCalculator())->refresh($studentIds);
        (new AutomationEngine())->plan();
        (new AutomationEngine())->dispatch();
    }

    private function sentKeys(int $studentId): array
    {
        return MessageSend::with('automation')->where('student_id', $studentId)->whereNotNull('sent_at')
            ->orderBy('sent_at')->get()->map(fn ($s) => $s->automation->key)->all();
    }

    // ------------------------------------------------------------ catalogue integrity

    public function test_catalogue_is_internally_consistent(): void
    {
        $templates = StarterCatalog::templates();
        $events = array_values((new ReflectionClass(EventTracker::class))->getConstants());

        foreach (StarterCatalog::automations() as $key => $a) {
            $this->assertArrayHasKey($a['template_key'], $templates, "{$key}: unknown template");
            $this->assertSame([], array_diff(array_keys($a['conditions'] ?? []), StudentFilter::KEYS), "{$key}: unknown condition key");
            if ($a['trigger_type'] === Automation::TRIGGER_EVENT) {
                $this->assertContains($a['trigger_event'], $events, "{$key}: unknown trigger event");
            }
            $this->assertContains($a['goal_event'], $events, "{$key}: unknown goal event");
        }

        foreach ($templates as $key => $t) {
            preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $t['subject'] . $t['preheader'] . $t['html_body'] . ($t['cta_label'] ?? ''), $m);
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), TemplateRenderer::VARIABLES)), "{$key}: unknown variable");
            $this->assertLessThanOrEqual(80, mb_strlen($t['subject']), "{$key}: subject too long");
            $this->assertNotEmpty($t['cta_label'] ?? null, "{$key}: one clear CTA");
        }
    }

    public function test_install_is_off_by_default_idempotent_and_force_keeps_on_off(): void
    {
        $this->assertSame(count(StarterCatalog::automations()), Automation::count());
        $this->assertSame(0, Automation::where('is_active', true)->count());

        EmailTemplate::where('key', 'welcome')->update(['subject' => 'Edited by admin']);
        $this->enable('welcome');
        $this->artisan('marketing:install-starter')->assertSuccessful();
        $this->assertSame('Edited by admin', EmailTemplate::where('key', 'welcome')->value('subject'));

        $this->artisan('marketing:install-starter', ['--force' => true])->assertSuccessful();
        $this->assertSame('Welcome to ExamsNepal, {{first_name}}', EmailTemplate::where('key', 'welcome')->value('subject'));
        $this->assertTrue(Automation::where('key', 'welcome')->value('is_active'));
    }

    public function test_every_template_renders_without_leftover_placeholders(): void
    {
        $id = $this->student(['name' => 'Anita Karki']);
        $this->attempt($id, ExamTypeEnum::FREE_QUIZ->value, 55, now()->subDay());
        (new StudentMetricsCalculator())->refresh([$id]);

        foreach (EmailTemplate::all() as $template) {
            $out = (new TemplateRenderer())->render($template, $id);
            $this->assertStringNotContainsString('{{', $out['subject'] . $out['html'] . $out['text'], $template->key);
            $this->assertStringContainsString('Anita', $out['subject'] . $out['html'], $template->key);
        }
    }

    // ------------------------------------------------------------ journeys

    public function test_activation_journey_is_spaced_by_the_cap_and_stops_after_first_exam(): void
    {
        $this->enable('welcome', 'activate_1', 'activate_2', 'activate_3');
        $start = CarbonImmutable::parse('2026-09-01 07:10', 'Asia/Kathmandu');
        $this->travelTo($start);
        $sita = $this->student(['created_at' => $start]);
        $ram = $this->student(['created_at' => $start]);

        foreach ([$sita, $ram] as $id) {
            (new EventTracker())->track($id, EventTracker::LOGGED_IN, [], 'web');
        }
        for ($h = 0; $h < 12 * 24; $h++) {
            $this->travelTo($start->addHours($h));
            if ($h === 100) { // Ram takes his first exam on day 4
                $this->attempt($ram, ExamTypeEnum::FREE_QUIZ->value, 60, now());
            }
            $this->tick([$sita, $ram]);
        }

        $this->assertSame(['welcome', 'activate_1', 'activate_2', 'activate_3'], $this->sentKeys($sita));
        $this->assertSame(['welcome', 'activate_1'], $this->sentKeys($ram));
    }

    public function test_welcome_needs_a_first_login_and_skips_old_accounts(): void
    {
        $this->enable('welcome');
        $new = $this->student(['created_at' => now()->subHour()]);
        $old = $this->student(['created_at' => now()->subDays(30)]);
        (new EventTracker())->track($new, EventTracker::LOGGED_IN);
        (new EventTracker())->track($old, EventTracker::LOGGED_IN);
        (new AutomationEngine())->dispatch();

        $this->assertSame(['welcome'], $this->sentKeys($new));
        $this->assertSame('conditions_not_met', MessageSend::where('student_id', $old)->value('suppress_reason'));
    }

    public function test_free_quiz_leads_to_mock_nudge_with_goal_attribution(): void
    {
        $this->enable('free_to_mock_1');
        $id = $this->student();
        $this->attempt($id, ExamTypeEnum::FREE_QUIZ->value, 64, now()->subDays(3));
        $this->attempt($id, ExamTypeEnum::FREE_QUIZ->value, 64, now());
        (new StudentMetricsCalculator())->refresh([$id]);

        // A mock submission does not trigger it; a free one does.
        (new EventTracker())->track($id, EventTracker::EXAM_SUBMITTED, ['exam_type' => 'MOCK_TEST']);
        $this->assertSame(0, MessageSend::count());
        (new EventTracker())->track($id, EventTracker::EXAM_SUBMITTED, ['exam_type' => 'FREE_QUIZ', 'score_pct' => 64.0]);
        $this->travel(31)->minutes();
        (new AutomationEngine())->dispatch();

        $send = MessageSend::sole();
        $this->assertSame(MessageSend::SENT, $send->status, (string) $send->suppress_reason);
        // (The earlier Mock in the same second doesn't count: goals must come after the trigger.)
        $this->assertSame('You scored 64%. Try it under real exam conditions', $send->subject);

        $this->travel(1)->day();
        (new EventTracker())->track($id, EventTracker::EXAM_SUBMITTED, ['exam_type' => 'SPRINT_QUIZ']);
        $this->assertNotNull($send->refresh()->goal_met_at, 'Sprint counts as the Mock/Sprint goal');
    }

    public function test_free_waiting_only_follows_an_earlier_mock_nudge_and_skips_payers(): void
    {
        $this->enable('free_to_mock_1', 'free_waiting');
        $id = $this->student();
        $this->attempt($id, ExamTypeEnum::FREE_QUIZ->value, 50, now());
        (new StudentMetricsCalculator())->refresh([$id]);
        $free = fn () => (new EventTracker())->track($id, EventTracker::EXAM_SUBMITTED, ['exam_type' => 'FREE_QUIZ']);

        $free();
        $this->travel(90)->minutes(); // 20:00, both due; the window closes at 20:30
        (new AutomationEngine())->dispatch();
        $this->assertSame(['free_to_mock_1'], $this->sentKeys($id));
        // First free quiz: the upsell is dropped (lower priority, and no earlier nudge yet).
        $this->assertSame(MessageSend::SUPPRESSED, MessageSend::whereHas('automation', fn ($q) => $q->where('key', 'free_waiting'))->value('status'));

        $this->travelTo(CarbonImmutable::parse('2026-10-03 18:30', 'Asia/Kathmandu'));
        $free();
        $this->travel(61)->minutes();
        (new AutomationEngine())->dispatch();
        $this->assertSame(['free_to_mock_1', 'free_waiting'], $this->sentKeys($id));
    }

    public function test_checkout_recovery_stops_once_they_pay(): void
    {
        $this->enable('checkout_abandoned_1', 'checkout_abandoned_2', 'paid_welcome');
        $id = $this->student();
        (new StudentMetricsCalculator())->refresh([$id]);

        (new EventTracker())->track($id, EventTracker::CHECKOUT_STARTED, ['subscriber_id' => 1]);
        $this->travel(61)->minutes();
        (new AutomationEngine())->dispatch();
        $this->assertSame(['checkout_abandoned_1'], $this->sentKeys($id));

        // They pay the next morning; the 48h reminder must not go out.
        $this->travelTo(CarbonImmutable::parse('2026-09-27 07:30', 'Asia/Kathmandu'));
        $this->payment($id, ['subscribed_at' => now()]);
        (new EventTracker())->track($id, EventTracker::PAYMENT_SUCCEEDED, ['subscriber_id' => 1]);
        $this->travelTo(CarbonImmutable::parse('2026-09-28 19:40', 'Asia/Kathmandu'));
        (new AutomationEngine())->dispatch();

        $this->assertNotNull(MessageSend::whereHas('automation', fn ($q) => $q->where('key', 'checkout_abandoned_1'))->value('goal_met_at'));
        $this->assertSame('goal_already_met', MessageSend::whereHas('automation', fn ($q) => $q->where('key', 'checkout_abandoned_2'))->value('suppress_reason'));
    }

    public function test_payment_failed_is_transactional_and_goes_out_at_night(): void
    {
        $this->enable('payment_failed');
        $this->travelTo(CarbonImmutable::parse('2026-09-26 23:10', 'Asia/Kathmandu'));
        $id = $this->student();
        (new EventTracker())->track($id, EventTracker::PAYMENT_FAILED, ['subscriber_id' => 9]);
        $this->travel(16)->minutes();
        (new AutomationEngine())->dispatch();

        $this->assertSame(['payment_failed'], $this->sentKeys($id));
    }

    public function test_renewal_reminders_and_upsell_rules(): void
    {
        $this->enable('expiring_7d', 'high_performer');
        $expiring = $this->student();
        $this->payment($expiring, ['end_date' => today()->addDays(7)]);
        foreach (range(1, 3) as $d) {
            $this->attempt($expiring, ExamTypeEnum::MOCK_TEST->value, 90, now()->subDays($d));
        }
        $freeStar = $this->student();
        foreach (range(1, 3) as $d) {
            $this->attempt($freeStar, ExamTypeEnum::FREE_QUIZ->value, 88, now()->subDays($d));
        }
        $this->tick([$expiring, $freeStar]);

        $this->assertSame(['expiring_7d'], $this->sentKeys($expiring)); // payer: renewal, never the upsell
        $this->assertSame(['high_performer'], $this->sentKeys($freeStar));
    }

    public function test_weekly_report_goes_out_only_on_sunday_morning(): void
    {
        $this->enable('weekly_progress_report');
        $id = $this->student();
        $this->attempt($id, ExamTypeEnum::FREE_QUIZ->value, 70, now()->subDays(2));

        $this->travelTo(CarbonImmutable::parse('2026-09-26 07:05', 'Asia/Kathmandu')); // Saturday
        $this->tick([$id]);
        $this->assertSame([], $this->sentKeys($id));

        $this->travelTo(CarbonImmutable::parse('2026-09-27 07:05', 'Asia/Kathmandu')); // Sunday
        $this->tick([$id]);
        $this->assertSame(['weekly_progress_report'], $this->sentKeys($id));
        $this->assertSame('Your ExamsNepal week: 1 exams', MessageSend::sole()->subject);
    }

    public function test_dormant_winback_matches_the_21_day_mark(): void
    {
        $this->enable('dormant_winback_21', 'dormant_winback_45');
        $dormant = $this->student();
        $this->attempt($dormant, ExamTypeEnum::FREE_QUIZ->value, 40, now()->subDays(22));
        $recent = $this->student();
        $this->attempt($recent, ExamTypeEnum::FREE_QUIZ->value, 40, now()->subDays(5));
        $this->tick([$dormant, $recent]);

        $this->assertSame(['dormant_winback_21'], $this->sentKeys($dormant));
        $this->assertSame([], $this->sentKeys($recent));
    }

    public function test_streak_reminder_pushes_app_users_who_have_not_practised_today(): void
    {
        $push = new class implements \App\Services\Marketing\Channels\PushSender {
            public array $to = [];
            public function send(int $studentId, string $token, string $title, string $body, array $data): void { $this->to[] = $studentId; }
        };
        $this->app->instance(\App\Services\Marketing\Channels\PushSender::class, $push);
        $this->enable('streak_reminder');
        $this->travelTo(CarbonImmutable::parse('2026-09-26 19:05', 'Asia/Kathmandu'));
        [$pending, $doneToday, $webOnly] = [$this->student(['fcm_token' => 'a']), $this->student(['fcm_token' => 'b']), $this->student()];
        foreach ([$pending, $doneToday, $webOnly] as $id) {
            $this->attempt($id, ExamTypeEnum::FREE_QUIZ->value, 50, now()->subDays(2));
            $this->attempt($id, ExamTypeEnum::FREE_QUIZ->value, 50, now()->subDay());
        }
        $this->attempt($doneToday, ExamTypeEnum::FREE_QUIZ->value, 50, now()->subHour());

        $this->tick([$pending, $doneToday, $webOnly]);

        $this->assertSame([$pending], $push->to);
        $this->assertSame(0, MessageSend::where('student_id', $webOnly)->count(), 'web-only students are never queued for a push-only automation');
    }

    public function test_unverified_never_active_addresses_are_skipped(): void
    {
        $this->enable('activate_1');
        $ghost = $this->student(['email_verified_at' => null, 'created_at' => now()->subHours(30)]);
        $legacy = $this->student(['email_verified_at' => null, 'created_at' => now()->subHours(30)]);
        DB::table('student_profiles')->where('id', $legacy)->update(['last_active_at' => now()->subHour()]);

        $this->tick([$ghost, $legacy]);

        $this->assertSame([], $this->sentKeys($ghost));
        $this->assertSame(['activate_1'], $this->sentKeys($legacy));
    }
}
