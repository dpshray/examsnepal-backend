<?php

namespace Tests\Feature\Marketing;

use App\Enums\ExamTypeEnum;
use App\Mail\Marketing\MarketingMessage;
use App\Models\Marketing\Automation;
use App\Models\Marketing\EmailTemplate;
use App\Models\Marketing\MessageSend;
use App\Services\Marketing\AutomationEngine;
use App\Services\Marketing\EventTracker;
use App\Services\Marketing\MarketingSettings;
use App\Services\Marketing\SendWindow;
use App\Services\Marketing\StudentMetricsCalculator;
use App\Services\Marketing\TemplateRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AutomationEngineTest extends MarketingDatabaseTestCase
{
    /** Saturday 18:30 NPT - inside the evening send window. */
    private const IN_WINDOW = '2026-09-26 18:30';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse(self::IN_WINDOW, 'Asia/Kathmandu'));
        MarketingSettings::set(MarketingSettings::PAUSED, false);
    }

    private function template(string $key, array $attrs = []): EmailTemplate
    {
        return EmailTemplate::create($attrs + [
            'key' => $key,
            'name' => $key,
            'category' => 'lifecycle',
            'subject' => 'Hi {{first_name}}, ready for {{target_exam}}?',
            'preheader' => 'Takes 10 minutes',
            'html_body' => '<p>Hello {{first_name}}, you have taken {{total_attempts}} exams. <a href="https://www.examsnepal.com/free-quiz">Free quiz</a></p>',
            'cta_label' => 'Take free quiz',
            'cta_path' => '/student/free-quiz',
        ]);
    }

    private function automation(string $key, array $attrs = []): Automation
    {
        if (!EmailTemplate::where('key', $attrs['template_key'] ?? $key)->exists()) {
            $this->template($attrs['template_key'] ?? $key, ['category' => $attrs['_category'] ?? 'lifecycle']);
        }
        unset($attrs['_category']);

        return Automation::create($attrs + [
            'key' => $key,
            'name' => $key,
            'trigger_type' => Automation::TRIGGER_SCHEDULED,
            'template_key' => $key,
            'cooldown_days' => 30,
            'priority' => 50,
            'is_active' => true,
        ]);
    }

    private function engine(): AutomationEngine
    {
        return new AutomationEngine();
    }

    private function sends(?string $status = null)
    {
        return MessageSend::query()->when($status, fn ($q) => $q->where('status', $status))->orderBy('id')->get();
    }

    // -------------------------------------------------------------- windows

    public function test_send_window(): void
    {
        $at = fn ($t) => CarbonImmutable::parse("2026-09-26 {$t}", 'Asia/Kathmandu');
        $this->assertTrue(SendWindow::isOpen($at('07:00')));
        $this->assertFalse(SendWindow::isOpen($at('09:00')));
        $this->assertTrue(SendWindow::isOpen($at('20:29')));
        $this->assertFalse(SendWindow::isOpen($at('20:30')));
        $this->assertEquals($at('18:00'), SendWindow::next($at('12:15')));
        $this->assertEquals($at('07:00')->addDay(), SendWindow::next($at('21:00')));
        $this->assertEquals($at('07:00'), SendWindow::next($at('02:00')));
        $this->assertEquals($at('08:10'), SendWindow::next($at('08:10')));
    }

    // -------------------------------------------------------------- event flow

    public function test_event_automation_queues_then_sends_with_unsubscribe_headers(): void
    {
        $this->automation('welcome', ['trigger_type' => Automation::TRIGGER_EVENT, 'trigger_event' => 'signed_up', 'goal_event' => 'exam_submitted']);
        $id = $this->student(['name' => 'sita sharma', 'email' => 'Sita@Example.com', 'created_at' => now()]);

        (new EventTracker())->track($id, EventTracker::SIGNED_UP, ['method' => 'email'], 'web');

        $send = $this->sends()->sole();
        $this->assertSame(MessageSend::QUEUED, $send->status);
        $this->assertSame('exam_submitted', $send->goal_event);

        $this->assertSame(1, $this->engine()->dispatch()['sent']);
        $send->refresh();
        $this->assertSame(MessageSend::SENT, $send->status);
        $this->assertSame('sita@example.com', $send->to_address);
        $this->assertSame('Hi Sita, ready for Medical?', $send->subject);

        Mail::assertSent(MarketingMessage::class, function (MarketingMessage $m) use ($send) {
            $headers = $m->headers()->text;
            return $m->hasTo('Sita@Example.com')
                && str_contains($headers['List-Unsubscribe'], '/e/u/' . $send->id)
                && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click'
                && $headers['X-ExamsNepal-Send-Id'] === (string) $send->id
                && str_contains($m->htmlBody, '/e/o/' . $send->id)      // open pixel
                && str_contains($m->htmlBody, '/e/c/' . $send->id)      // tracked links
                && str_contains($m->htmlBody, 'utm_campaign%3Dwelcome') // UTM on the destination
                && str_contains($m->textBody, 'Unsubscribe from these emails');
        });
    }

    public function test_delay_and_send_window_push_the_due_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00', 'Asia/Kathmandu'));
        $this->automation('checkout_help', ['trigger_type' => Automation::TRIGGER_EVENT, 'trigger_event' => 'checkout_started', 'delay_minutes' => 60]);
        $id = $this->student();

        (new EventTracker())->track($id, EventTracker::CHECKOUT_STARTED, ['subscriber_id' => 1], 'web');

        // 13:00 is outside the windows -> next window opens at 18:00.
        $this->assertEquals(CarbonImmutable::parse('2026-09-26 18:00', 'Asia/Kathmandu'), $this->sends()->sole()->scheduled_for);
        $this->assertSame(0, $this->engine()->dispatch()['sent']);
        $this->travelTo(CarbonImmutable::parse('2026-09-26 18:01', 'Asia/Kathmandu'));
        $this->assertSame(1, $this->engine()->dispatch()['sent']);
    }

    public function test_duplicate_events_do_not_queue_twice(): void
    {
        $this->automation('welcome', ['trigger_type' => Automation::TRIGGER_EVENT, 'trigger_event' => 'signed_up']);
        $id = $this->student();
        (new EventTracker())->track($id, EventTracker::SIGNED_UP);
        (new EventTracker())->track($id, EventTracker::SIGNED_UP);

        $this->assertCount(1, $this->sends());
    }

    // -------------------------------------------------------------- guards at send time

    public function test_conditions_are_rechecked_at_send_time(): void
    {
        $this->automation('activate_1', ['conditions' => ['attempts_max' => 0]]);
        $id = $this->student();
        (new StudentMetricsCalculator())->refresh([$id]);
        $this->assertSame(1, $this->engine()->plan());

        // Student takes an exam before the message goes out.
        $this->attempt($id, ExamTypeEnum::FREE_QUIZ->value, 60, now());
        $this->engine()->dispatch();

        $this->assertSame('conditions_not_met', $this->sends()->sole()->suppress_reason);
        Mail::assertNothingSent();
    }

    public function test_goal_met_before_send_suppresses(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00', 'Asia/Kathmandu'));
        $this->automation('mock_nudge', ['trigger_type' => Automation::TRIGGER_EVENT, 'trigger_event' => 'exam_submitted', 'goal_event' => 'exam_submitted', 'goal_properties' => ['exam_type' => 'MOCK_TEST']]);
        $id = $this->student();
        $tracker = new EventTracker();
        $tracker->track($id, EventTracker::EXAM_SUBMITTED, ['exam_type' => 'FREE_QUIZ']);
        $this->travel(30)->minutes();
        $tracker->track($id, EventTracker::EXAM_SUBMITTED, ['exam_type' => 'MOCK_TEST']);

        $this->travelTo(CarbonImmutable::parse(self::IN_WINDOW, 'Asia/Kathmandu'));
        $this->engine()->dispatch();

        $this->assertSame('goal_already_met', $this->sends()->sole()->suppress_reason);
    }

    public function test_upsell_never_reaches_paying_students(): void
    {
        $this->automation('see_plans', ['is_upsell' => true]);
        $payer = $this->student();
        $this->payment($payer);
        $free = $this->student();
        (new StudentMetricsCalculator())->refresh([$payer, $free]);

        $this->assertSame(1, $this->engine()->plan());
        $this->assertSame($free, $this->sends()->sole()->student_id);
    }

    public function test_kill_switch_holds_everything_and_stale_messages_expire(): void
    {
        $this->automation('welcome', ['trigger_type' => Automation::TRIGGER_EVENT, 'trigger_event' => 'signed_up']);
        $id = $this->student();
        (new EventTracker())->track($id, EventTracker::SIGNED_UP);
        MarketingSettings::set(MarketingSettings::PAUSED, true);

        $this->assertSame(0, $this->engine()->dispatch()['sent']);
        $this->assertSame(MessageSend::QUEUED, $this->sends()->sole()->status);

        $this->travel(4)->days();
        MarketingSettings::set(MarketingSettings::PAUSED, false);
        $this->engine()->dispatch();
        $this->assertSame('expired', $this->sends()->sole()->suppress_reason);
        Mail::assertNothingSent();
    }

    public function test_kill_switch_defaults_to_paused(): void
    {
        DB::table('marketing_settings')->delete();
        $this->assertTrue(MarketingSettings::paused());
    }

    public function test_transactional_ignores_window_caps_and_kill_switch_but_not_suppressions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 23:00', 'Asia/Kathmandu'));
        MarketingSettings::set(MarketingSettings::PAUSED, true);
        $this->automation('receipt', ['trigger_type' => Automation::TRIGGER_EVENT, 'trigger_event' => 'payment_succeeded', '_category' => 'transactional']);
        $ok = $this->student();
        $bounced = $this->student(['email' => 'gone@example.com']);
        DB::table('suppressions')->insert(['email' => 'gone@example.com', 'reason' => 'hard_bounce']);

        (new EventTracker())->track($ok, EventTracker::PAYMENT_SUCCEEDED);
        (new EventTracker())->track($bounced, EventTracker::PAYMENT_SUCCEEDED);
        $stats = $this->engine()->dispatch();

        $this->assertSame(1, $stats['sent']);
        $this->assertSame('suppressed_address', $this->sends()->firstWhere('student_id', $bounced)->suppress_reason);
    }

    public function test_only_the_highest_priority_message_goes_out(): void
    {
        $this->automation('low', ['priority' => 10]);
        $this->automation('high', ['priority' => 90]);
        $id = $this->student();
        (new StudentMetricsCalculator())->refresh([$id]);

        $this->engine()->plan();
        $this->engine()->dispatch();

        $sent = $this->sends(MessageSend::SENT)->sole();
        $this->assertSame('high', $sent->automation->key);
        $this->assertCount(1, Mail::sent(MarketingMessage::class));
    }

    public function test_event_message_of_higher_priority_displaces_queued_lower_one(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00', 'Asia/Kathmandu'));
        $this->automation('weekly', ['priority' => 10]);
        $this->automation('checkout_help', ['trigger_type' => Automation::TRIGGER_EVENT, 'trigger_event' => 'checkout_started', 'priority' => 95]);
        $id = $this->student();
        (new StudentMetricsCalculator())->refresh([$id]);
        $this->engine()->plan();
        (new EventTracker())->track($id, EventTracker::CHECKOUT_STARTED);

        $this->travelTo(CarbonImmutable::parse(self::IN_WINDOW, 'Asia/Kathmandu'));
        $this->engine()->dispatch();

        $byKey = $this->sends()->keyBy(fn ($s) => $s->automation->key);
        $this->assertSame(MessageSend::SENT, $byKey['checkout_help']->status);
        $this->assertSame('lower_priority', $byKey['weekly']->suppress_reason);
    }

    /** Acceptance: nobody ever gets more than 1 per 48h or 3 per 7 days. */
    public function test_frequency_cap_holds_over_weeks(): void
    {
        foreach (range(1, 6) as $i) {
            $this->automation("auto_{$i}", ['priority' => $i * 10, 'cooldown_days' => 1]);
        }
        $students = array_map(fn () => $this->student(), range(1, 3));

        $start = CarbonImmutable::parse('2026-09-01 00:00', 'Asia/Kathmandu');
        for ($day = 0; $day < 21; $day++) {
            foreach (['07:30', '18:30'] as $time) {
                $this->travelTo($start->addDays($day)->setTimeFromTimeString($time));
                (new StudentMetricsCalculator())->refresh($students);
                $this->engine()->plan();
                $this->engine()->dispatch();
            }
        }

        foreach ($students as $id) {
            $times = MessageSend::where('student_id', $id)->whereNotNull('sent_at')->orderBy('sent_at')->pluck('sent_at')->values();
            $this->assertGreaterThanOrEqual(7, $times->count(), 'engine should keep sending within the cap');
            foreach ($times as $i => $t) {
                if ($i > 0) {
                    $this->assertGreaterThanOrEqual(48, $times[$i - 1]->diffInHours($t), "student {$id}: two sends within 48h");
                }
                $inWeek = $times->filter(fn ($o) => $o->gte($t) && $o->lt($t->copy()->addDays(7)))->count();
                $this->assertLessThanOrEqual(3, $inWeek, "student {$id}: more than 3 in 7 days");
            }
        }
    }

    // -------------------------------------------------------------- dry run

    /** Acceptance: the dry-run preview matches what actually gets queued and sent. */
    public function test_preview_matches_actual_sends(): void
    {
        $auto = $this->automation('activate_1', ['conditions' => ['attempts_max' => 0]]);
        $a = $this->student();
        $b = $this->student(['marketing_email_opt_in' => false]);
        $c = $this->student(['email' => 'not-an-email']);
        $d = $this->student();
        $this->attempt($d, ExamTypeEnum::FREE_QUIZ->value, 50, now()->subDay());
        $e = $this->student();
        DB::table('suppressions')->insert(['email' => DB::table('student_profiles')->where('id', $e)->value('email'), 'reason' => 'unsubscribed']);
        (new StudentMetricsCalculator())->refreshAll();

        $preview = $this->engine()->preview($auto)[0];
        $this->assertSame(4, $preview['candidates']); // d took an exam
        $this->assertSame(['unsubscribed' => 1, 'no_email' => 1, 'suppressed_address' => 1], $preview['skipped']);
        $previewIds = collect($preview['sample'])->pluck('student_id')->all();

        $this->engine()->plan();
        $this->engine()->dispatch();

        $this->assertSame($previewIds, $this->sends(MessageSend::SENT)->pluck('student_id')->all());
        $this->assertSame([$a], $previewIds);
    }

    /** Bulk mode (planner / dry run) must decide exactly like the per-student checks. */
    public function test_bulk_primed_guard_matches_per_student_guard(): void
    {
        $auto = $this->automation('upsell', ['is_upsell' => true, 'priority' => 50, 'cooldown_days' => 10]);
        $this->automation('higher', ['priority' => 90]);
        $this->automation('lower', ['priority' => 10]);
        $ids = [
            'plain' => $this->student(),
            'suppressed' => $this->student(['email' => 'blocked@example.com']),
            'cooldown' => $this->student(),
            'capped' => $this->student(),
            'paying' => $this->student(),
            'higher_queued' => $this->student(),
            'lower_queued' => $this->student(),
            'week_full' => $this->student(),
        ];
        DB::table('suppressions')->insert(['email' => 'blocked@example.com', 'reason' => 'hard_bounce']);
        MessageSend::create(['student_id' => $ids['cooldown'], 'automation_id' => $auto->id, 'template_key' => 'upsell', 'status' => 'sent', 'sent_at' => now()->subDays(5)]);
        MessageSend::create(['student_id' => $ids['capped'], 'template_key' => 'x', 'status' => 'sent', 'sent_at' => now()->subHours(20)]);
        foreach ([3, 5] as $d) {
            MessageSend::create(['student_id' => $ids['week_full'], 'template_key' => 'x', 'status' => 'opened', 'sent_at' => now()->subDays($d)]);
        }
        MessageSend::create(['student_id' => $ids['week_full'], 'template_key' => 'x', 'status' => 'clicked', 'sent_at' => now()->subDays(6)]);
        $this->payment($ids['paying']);
        MessageSend::create(['student_id' => $ids['higher_queued'], 'automation_id' => Automation::where('key', 'higher')->value('id'), 'template_key' => 'higher', 'status' => 'queued', 'scheduled_for' => now()->addDay()]);
        MessageSend::create(['student_id' => $ids['lower_queued'], 'automation_id' => Automation::where('key', 'lower')->value('id'), 'template_key' => 'lower', 'status' => 'queued', 'scheduled_for' => now()->addDay()]);
        (new StudentMetricsCalculator())->refreshAll();

        $bulk = collect((new AutomationEngine())->preview($auto, 100)[0]['skipped'])->all();
        $students = DB::table('student_profiles as p')->leftJoin('student_metrics as m', 'm.student_id', '=', 'p.id')
            ->get(['p.id', 'p.name', 'p.email', 'p.phone', 'p.fcm_token', 'p.marketing_email_opt_in', 'p.unsubscribed_at', 'p.email_verified_at', 'p.last_active_at', 'm.total_attempts']);
        $single = $students->map(fn ($st) => (new \App\Services\Marketing\SendGuard(now()))->check($st, 'lifecycle', $auto))->filter()->countBy()->all();

        $this->assertEquals($single, $bulk);
        $this->assertEquals(['suppressed_address' => 1, 'cooldown' => 1, 'frequency_cap' => 3, 'paying_student' => 1], $bulk);
    }

    public function test_preview_includes_inactive_automations_without_sending(): void
    {
        $auto = $this->automation('draft', ['is_active' => false]);
        $this->student();
        (new StudentMetricsCalculator())->refreshAll();

        $this->assertSame(1, $this->engine()->preview($auto)[0]['would_send']);
        $this->assertSame(0, $this->engine()->plan());
    }

    // -------------------------------------------------------------- attribution

    public function test_goal_attribution_within_72_hours(): void
    {
        $this->automation('nudge', ['goal_event' => 'exam_submitted']);
        $early = $this->student();
        $late = $this->student();
        (new StudentMetricsCalculator())->refresh([$early, $late]);
        $this->engine()->plan();
        $this->engine()->dispatch();

        $this->travel(71)->hours();
        (new EventTracker())->track($early, EventTracker::EXAM_SUBMITTED);
        $this->travel(2)->hours();
        (new EventTracker())->track($late, EventTracker::EXAM_SUBMITTED);

        $this->assertNotNull(MessageSend::firstWhere('student_id', $early)->goal_met_at);
        $this->assertNull(MessageSend::firstWhere('student_id', $late)->goal_met_at);
    }

    public function test_ab_variant_is_stable_and_uses_its_template(): void
    {
        $auto = $this->automation('ab', ['variant_b_template_key' => 'ab_b', 'ab_split_pct' => 50]);
        $this->template('ab_b', ['subject' => 'Variant B']);
        $variants = collect(range(1, 200))->map(fn ($i) => $auto->variantFor($i));

        $this->assertSame($variants->all(), collect(range(1, 200))->map(fn ($i) => $auto->variantFor($i))->all());
        $this->assertEqualsWithDelta(100, $variants->filter(fn ($v) => $v === 'A')->count(), 25);
        $this->assertSame('ab_b', $auto->templateKeyFor('B'));
        $this->assertSame('ab', $auto->templateKeyFor('A'));
    }

    // -------------------------------------------------------------- rendering

    public function test_renderer_fills_variables_and_escapes_html(): void
    {
        $template = $this->template('t', ['html_body' => '<p>{{first_name}} · {{ avg_score }}% · {{unknown}}</p>']);
        $id = $this->student(['name' => '<b>ram</b> bahadur']);

        $out = (new TemplateRenderer())->render($template, $id);

        // Name is escaped (and title-cased); unknown and empty variables render as nothing.
        $this->assertStringContainsString('<p>&lt;B&gt;Ram&lt;/B&gt; · % · </p>', $out['html']);
        $this->assertStringNotContainsString('<b>', $out['html']);
        $this->assertStringContainsString('https://www.examsnepal.com/student/free-quiz', $out['html']);
        $this->assertStringNotContainsString('/e/c/', $out['html']); // previews aren't tracked
    }

    public function test_utm_is_added_only_to_our_links(): void
    {
        $this->assertSame(
            'https://www.examsnepal.com/pricing?plan=3&utm_source=email&utm_medium=lifecycle&utm_campaign=x#top',
            TemplateRenderer::withUtm('https://www.examsnepal.com/pricing?plan=3#top', 'x'),
        );
        $this->assertSame('https://esewa.com.np/', TemplateRenderer::withUtm('https://esewa.com.np/', 'x'));
    }
}
