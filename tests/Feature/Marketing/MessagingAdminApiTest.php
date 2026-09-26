<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\Automation;
use App\Models\Marketing\EmailTemplate;
use App\Models\Marketing\MessageSend;
use App\Services\Marketing\AutomationEngine;
use App\Services\Marketing\MarketingSettings;
use App\Services\Marketing\StudentMetricsCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MessagingAdminApiTest extends MarketingDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Skip admin auth only; route-model binding must stay on.
        $this->withoutMiddleware([\Illuminate\Auth\Middleware\Authenticate::class, \App\Http\Middleware\CheckUserRole::class, \App\Http\Middleware\AuthenticateApi::class]);
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 18:30', 'Asia/Kathmandu'));
        EmailTemplate::create(['key' => 'nudge', 'name' => 'Nudge', 'subject' => 'Hi {{first_name}}', 'html_body' => '<p>Hi</p>']);
        EmailTemplate::create(['key' => 'nudge_b', 'name' => 'Nudge B', 'subject' => 'Hey', 'html_body' => '<p>Hey</p>']);
    }

    public function test_kill_switch_endpoint(): void
    {
        $this->getJson('/api/admin/marketing/messaging/status')->assertOk()->assertJsonPath('data.paused', true);
        $this->postJson('/api/admin/marketing/messaging/pause', ['paused' => false])->assertOk();
        $this->assertFalse(MarketingSettings::paused());
        $this->postJson('/api/admin/marketing/messaging/pause', ['paused' => true])->assertOk();
        $this->assertTrue(MarketingSettings::paused());
    }

    public function test_automation_list_stats_update_and_preview(): void
    {
        MarketingSettings::set(MarketingSettings::PAUSED, false);
        $auto = Automation::create([
            'key' => 'nudge', 'name' => 'Nudge', 'trigger_type' => 'scheduled', 'template_key' => 'nudge',
            'variant_b_template_key' => 'nudge_b', 'is_active' => true, 'goal_event' => 'exam_submitted',
        ]);
        $ids = array_map(fn () => $this->student(), range(1, 6));
        (new StudentMetricsCalculator())->refresh($ids);

        $this->getJson("/api/admin/marketing/automations/{$auto->id}/preview")->assertOk()->assertJsonPath('data.would_send', 6);
        (new AutomationEngine())->plan();
        (new AutomationEngine())->dispatch();
        MessageSend::first()->update(['opened_at' => now(), 'goal_met_at' => now()]);

        $row = $this->getJson('/api/admin/marketing/automations')->assertOk()->json('data.automations.0');
        $this->assertSame('Nudge', $row['template_name']);
        $this->assertSame(6, collect($row['stats'])->sum('sent'));
        $this->assertSame(1, collect($row['stats'])->sum('goal_met'));
        $this->assertEqualsCanonicalizing(['A', 'B'], collect($row['stats'])->pluck('variant')->unique()->values()->all());

        $this->patchJson("/api/admin/marketing/automations/{$auto->id}", ['is_active' => false, 'priority' => 80])->assertOk();
        $this->assertFalse($auto->fresh()->is_active);
        $this->patchJson("/api/admin/marketing/automations/{$auto->id}", ['priority' => -1])->assertStatus(422);

        $this->getJson('/api/admin/marketing/sends?status=sent')->assertOk()->assertJsonPath('data.total', 6);
    }

    public function test_suppression_management(): void
    {
        $id = $this->student(['email' => 'x@example.com', 'marketing_email_opt_in' => false, 'unsubscribed_at' => now()]);
        DB::table('suppressions')->insert(['email' => 'x@example.com', 'reason' => 'unsubscribed']);

        $this->postJson('/api/admin/marketing/suppressions', ['email' => 'blocked@example.com'])->assertCreated();
        $this->getJson('/api/admin/marketing/suppressions?q=blocked')->assertOk()->assertJsonPath('data.total', 1);

        $sid = DB::table('suppressions')->where('email', 'x@example.com')->value('id');
        $this->deleteJson("/api/admin/marketing/suppressions/{$sid}")->assertOk();
        $this->assertTrue((bool) DB::table('student_profiles')->where('id', $id)->value('marketing_email_opt_in'));
    }

    public function test_template_preview_renders_with_a_real_student(): void
    {
        $id = $this->student(['name' => 'Gita Rai']);

        $this->getJson("/api/admin/marketing/templates/nudge/preview?student_id={$id}")
            ->assertOk()->assertJsonPath('data.subject', 'Hi Gita');
        Mail::assertNothingSent();
    }
}
