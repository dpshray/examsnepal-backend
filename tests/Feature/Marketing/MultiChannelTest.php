<?php

namespace Tests\Feature\Marketing;

use App\Http\Middleware\CheckTokenVersionMiddleware;
use App\Models\Marketing\Automation;
use App\Models\Marketing\EmailTemplate;
use App\Models\Marketing\MessageSend;
use App\Models\StudentProfile;
use App\Services\Marketing\AutomationEngine;
use App\Services\Marketing\Channels\ChannelRouter;
use App\Services\Marketing\Channels\PushSender;
use App\Services\Marketing\Channels\SmsGateway;
use App\Services\Marketing\EventTracker;
use App\Services\Marketing\MarketingSettings;
use App\Services\Marketing\StudentMetricsCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MultiChannelTest extends MarketingDatabaseTestCase
{
    private FakePush $push;
    private FakeSms $sms;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->push = new FakePush();
        $this->sms = new FakeSms();
        $this->app->instance(PushSender::class, $this->push);
        $this->app->instance(SmsGateway::class, $this->sms);
        $this->travelTo(CarbonImmutable::parse('2026-09-26 18:30', 'Asia/Kathmandu'));
        MarketingSettings::set(MarketingSettings::PAUSED, false);
        EmailTemplate::create(['key' => 'renew', 'name' => 'Renew', 'subject' => 'Your plan ends tomorrow', 'preheader' => 'Renew today', 'html_body' => '<p>x</p>', 'cta_label' => 'Renew', 'cta_path' => '/student/subscription']);
    }

    private function automation(array $attrs): Automation
    {
        return Automation::create($attrs + ['key' => 'renew', 'name' => 'Renew', 'trigger_type' => 'event', 'trigger_event' => 'logged_in', 'template_key' => 'renew', 'is_active' => true, 'channel' => 'auto']);
    }

    private function trigger(int $id): MessageSend
    {
        (new StudentMetricsCalculator())->refresh([$id]);
        (new EventTracker())->track($id, EventTracker::LOGGED_IN);
        (new AutomationEngine())->dispatch();
        return MessageSend::where('student_id', $id)->latest('id')->first();
    }

    public function test_nepali_mobile_normalisation(): void
    {
        $this->assertSame('9841234567', ChannelRouter::nepaliMobile('+977-984-1234567'));
        $this->assertSame('9761234567', ChannelRouter::nepaliMobile('9779761234567'));
        $this->assertNull(ChannelRouter::nepaliMobile('01-4412345')); // landline
        $this->assertNull(ChannelRouter::nepaliMobile(null));
    }

    public function test_auto_prefers_push_when_the_app_is_installed(): void
    {
        $this->automation([]);
        $send = $this->trigger($this->student(['fcm_token' => 'device-1']));

        $this->assertSame(['push', 'sent', 'push'], [$send->channel, $send->status, $send->to_address]);
        $this->assertSame('Your plan ends tomorrow', $this->push->sent[0]['title']);
        $this->assertSame('Renew today', $this->push->sent[0]['body']);
        $this->assertStringContainsString('/e/c/' . $send->id, $this->push->sent[0]['data']['url']);
        Mail::assertNothingSent();
    }

    public function test_auto_falls_back_to_email_then_sms_only_when_allowed(): void
    {
        $this->automation([]);
        $emailOnly = $this->trigger($this->student());
        $this->assertSame(['email', 'sent'], [$emailOnly->channel, $emailOnly->status]);

        $noEmail = $this->student(['email' => 'not-an-email', 'phone' => '9841234567']);
        $this->assertSame('no_reachable_channel', $this->trigger($noEmail)->suppress_reason);

        Automation::where('key', 'renew')->update(['allow_sms' => true]);
        $sms = $this->trigger($this->student(['email' => 'also-bad', 'phone' => '+977 9812345678']));
        $this->assertSame(['sms', 'sent', '9812345678'], [$sms->channel, $sms->status, $sms->to_address]);
        $this->assertStringStartsWith('ExamsNepal: Your plan ends tomorrow https://www.examsnepal.com/student/subscription', $this->sms->sent[0]['text']);
    }

    public function test_marketing_opt_out_covers_push_too(): void
    {
        $this->automation([]);
        $send = $this->trigger($this->student(['fcm_token' => 'device-1', 'marketing_email_opt_in' => false, 'unsubscribed_at' => now()]));

        $this->assertSame('unsubscribed', $send->suppress_reason);
        $this->assertSame([], $this->push->sent);
    }

    public function test_push_failure_is_recorded(): void
    {
        $this->automation([]);
        $this->push->fail = true;
        $send = $this->trigger($this->student(['fcm_token' => 'expired-token']));

        $this->assertSame(MessageSend::FAILED, $send->status);
        $this->assertStringContainsString('token not registered', $send->error);
    }

    public function test_frequency_caps_are_per_channel(): void
    {
        $this->automation([]);
        $id = $this->student(['fcm_token' => 'device-1']);
        // An email yesterday doesn't block a push today...
        MessageSend::create(['student_id' => $id, 'channel' => 'email', 'template_key' => 'x', 'status' => 'sent', 'sent_at' => now()->subDay()]);
        $this->assertSame('sent', $this->trigger($id)->status);

        // ...but a push 10 hours ago does (push cap: 1 per 20h).
        Automation::where('key', 'renew')->update(['cooldown_days' => 0]);
        $this->travelTo(CarbonImmutable::parse('2026-09-27 07:30', 'Asia/Kathmandu')); // 13h later
        $this->assertSame('frequency_cap', $this->trigger($id)->suppress_reason);
    }

    public function test_banner_endpoint_returns_the_stage_banner(): void
    {
        $this->withoutMiddleware(CheckTokenVersionMiddleware::class);
        $id = $this->student(['created_at' => now()->subDays(3)]);
        $student = StudentProfile::query()->find($id);

        $this->actingAs($student, 'api')->getJson('/api/student/marketing/banner')
            ->assertOk()
            ->assertJsonPath('data.stage', 'registered_inactive')
            ->assertJsonPath('data.banner.cta_path', '/student/exams/free-quiz');

        $this->payment($id);
        DB::table('student_exams')->insert(['exam_id' => $this->exam(1), 'student_id' => $id, 'is_exam_completed' => 1, 'submitted_at' => now(), 'score_pct' => 50]);
        $this->actingAs($student, 'api')->getJson('/api/student/marketing/banner')
            ->assertJsonPath('data.stage', 'paid_active')
            ->assertJsonPath('data.banner', null);
    }
}

class FakePush implements PushSender
{
    public array $sent = [];
    public bool $fail = false;

    public function send(int $studentId, string $token, string $title, string $body, array $data): void
    {
        if ($this->fail) {
            throw new \RuntimeException('FCM: token not registered');
        }
        $this->sent[] = compact('studentId', 'token', 'title', 'body', 'data');
    }
}

class FakeSms implements SmsGateway
{
    public array $sent = [];

    public function send(string $phone, string $text): void
    {
        $this->sent[] = compact('phone', 'text');
    }
}
