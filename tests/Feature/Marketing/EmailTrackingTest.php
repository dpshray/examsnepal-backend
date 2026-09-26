<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\Automation;
use App\Models\Marketing\EmailTemplate;
use App\Models\Marketing\MessageSend;
use App\Services\Marketing\AutomationEngine;
use App\Services\Marketing\EventTracker;
use App\Services\Marketing\MarketingSettings;
use App\Services\Marketing\StudentMetricsCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailTrackingTest extends MarketingDatabaseTestCase
{
    private int $studentId;
    private MessageSend $send;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 18:30', 'Asia/Kathmandu'));
        MarketingSettings::set(MarketingSettings::PAUSED, false);

        EmailTemplate::create(['key' => 'nudge', 'name' => 'Nudge', 'subject' => 'Hi', 'html_body' => '<p>Hi</p>']);
        Automation::create(['key' => 'nudge', 'name' => 'Nudge', 'trigger_type' => 'scheduled', 'template_key' => 'nudge', 'is_active' => true, 'goal_event' => 'email_clicked']);
        $this->studentId = $this->student(['email' => 'hari@example.com']);
        (new StudentMetricsCalculator())->refresh([$this->studentId]);
        (new AutomationEngine())->plan();
        (new AutomationEngine())->dispatch();
        $this->send = MessageSend::sole();
    }

    public function test_open_pixel_marks_opened_once(): void
    {
        $this->get(URL::signedRoute('marketing.open', ['send' => $this->send->id]))
            ->assertOk()->assertHeader('Content-Type', 'image/gif');

        $this->send->refresh();
        $this->assertSame(MessageSend::OPENED, $this->send->status);
        $first = $this->send->opened_at;
        $this->travel(1)->hour();
        $this->get(URL::signedRoute('marketing.open', ['send' => $this->send->id]))->assertOk();
        $this->assertEquals($first, $this->send->refresh()->opened_at);
    }

    public function test_click_logs_event_attributes_goal_and_redirects(): void
    {
        $target = 'https://www.examsnepal.com/student/free-quiz?utm_source=email';

        $this->get(URL::signedRoute('marketing.click', ['send' => $this->send->id, 'u' => $target]))
            ->assertRedirect($target);

        $this->send->refresh();
        $this->assertSame(MessageSend::CLICKED, $this->send->status);
        $this->assertNotNull($this->send->opened_at);
        $this->assertNotNull($this->send->goal_met_at); // goal was email_clicked
        $this->assertSame(1, DB::table('events')->where('name', EventTracker::EMAIL_CLICKED)->count());
    }

    public function test_tampered_or_unsigned_links_are_rejected(): void
    {
        $signed = URL::signedRoute('marketing.click', ['send' => $this->send->id, 'u' => 'https://www.examsnepal.com/']);
        $this->get(str_replace(urlencode('https://www.examsnepal.com/'), urlencode('https://evil.example/'), $signed))->assertForbidden();
        $this->get("/e/u/{$this->send->id}")->assertForbidden();
        $this->get(URL::signedRoute('marketing.click', ['send' => $this->send->id, 'u' => 'javascript:alert(1)']))->assertNotFound();
    }

    /** Acceptance: one click unsubscribes, and every automation respects it. */
    public function test_one_click_unsubscribe_is_respected_everywhere(): void
    {
        $this->get(URL::signedRoute('marketing.unsubscribe', ['send' => $this->send->id]))
            ->assertOk()->assertSee("You've been unsubscribed", false);

        $profile = DB::table('student_profiles')->find($this->studentId);
        $this->assertFalse((bool) $profile->marketing_email_opt_in);
        $this->assertNotNull($profile->unsubscribed_at);
        $this->assertSame('unsubscribed', DB::table('suppressions')->where('email', 'hari@example.com')->value('reason'));
        $this->assertNotNull($this->send->refresh()->unsubscribed_at);

        // Another automation (event-triggered this time) later tries to reach them.
        EmailTemplate::create(['key' => 'other', 'name' => 'Other', 'subject' => 'Hi', 'html_body' => '<p>x</p>']);
        Automation::create(['key' => 'other', 'name' => 'Other', 'trigger_type' => 'event', 'trigger_event' => 'logged_in', 'template_key' => 'other', 'is_active' => true]);
        $this->travel(3)->days();
        (new EventTracker())->track($this->studentId, EventTracker::LOGGED_IN);
        (new AutomationEngine())->dispatch();

        $this->assertSame('suppressed_address', MessageSend::where('template_key', 'other')->sole()->suppress_reason);
        Mail::assertSentCount(1); // only the original
    }

    public function test_rfc8058_post_unsubscribes_without_csrf(): void
    {
        $this->post(URL::signedRoute('marketing.unsubscribe', ['send' => $this->send->id]), ['List-Unsubscribe' => 'One-Click'])
            ->assertOk();

        $this->assertFalse((bool) DB::table('student_profiles')->where('id', $this->studentId)->value('marketing_email_opt_in'));
    }

    public function test_resubscribe_lifts_only_the_unsubscribe(): void
    {
        $this->get(URL::signedRoute('marketing.unsubscribe', ['send' => $this->send->id]));
        $this->post(URL::signedRoute('marketing.resubscribe', ['send' => $this->send->id]))
            ->assertOk()->assertSee("You're subscribed again", false);

        $this->assertTrue((bool) DB::table('student_profiles')->where('id', $this->studentId)->value('marketing_email_opt_in'));
        $this->assertSame(0, DB::table('suppressions')->count());
    }
}
