<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\Automation;
use App\Models\Marketing\Broadcast;
use App\Models\Marketing\EmailTemplate;
use App\Models\Marketing\MessageSend;
use App\Services\Marketing\AbTestAnalyzer;
use App\Services\Marketing\AutomationEngine;
use App\Services\Marketing\MarketingSettings;
use App\Services\Marketing\StudentMetricsCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BroadcastsAndTemplatesTest extends MarketingDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Auth\Middleware\Authenticate::class, \App\Http\Middleware\CheckUserRole::class, \App\Http\Middleware\AuthenticateApi::class]);
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 18:30', 'Asia/Kathmandu'));
        MarketingSettings::set(MarketingSettings::PAUSED, false);
        EmailTemplate::create(['key' => 'news', 'name' => 'News', 'category' => 'promotional', 'subject' => 'New mocks for {{target_exam}}', 'html_body' => '<p>Hi {{first_name}}</p>', 'cta_label' => 'See mocks', 'cta_path' => '/student/exams/mock-tests']);
    }

    private function students(int $n, array $attrs = []): array
    {
        $ids = array_map(fn () => $this->student($attrs), range(1, $n));
        (new StudentMetricsCalculator())->refresh($ids);
        return $ids;
    }

    // ------------------------------------------------------------ broadcasts

    public function test_broadcast_preview_schedule_release_and_send(): void
    {
        [$a, $b] = $this->students(2);
        $this->students(1, ['exam_type_id' => 2]);
        DB::table('student_profiles')->where('id', $b)->update(['marketing_email_opt_in' => false, 'unsubscribed_at' => now()]);

        $preview = $this->postJson('/api/admin/marketing/broadcasts/preview', ['template_key' => 'news', 'filters' => ['exam_type_id' => 1]])
            ->assertOk()->json('data');
        $this->assertSame([2, 1, ['unsubscribed' => 1]], [$preview['candidates'], $preview['would_send'], $preview['skipped']]);
        $this->assertSame(1, $preview['estimated_days']);

        $this->postJson('/api/admin/marketing/broadcasts', ['name' => 'Everyone', 'template_key' => 'news', 'filters' => []])->assertStatus(422);
        $later = $this->postJson('/api/admin/marketing/broadcasts', ['name' => 'Later', 'template_key' => 'news', 'filters' => ['exam_type_id' => 1], 'scheduled_for' => '2026-09-27T01:15:00.000Z'])->json('data.id');
        $this->assertSame('2026-09-27 07:00:00', Broadcast::find($later)->scheduled_for->format('Y-m-d H:i:s')); // 01:15 UTC = 07:00 NPT
        Broadcast::find($later)->delete();
        $id = $this->postJson('/api/admin/marketing/broadcasts', ['name' => 'Medical news', 'template_key' => 'news', 'filters' => ['exam_type_id' => 1]])
            ->assertCreated()->json('data.id');

        $stats = (new AutomationEngine())->dispatch();

        $this->assertSame(1, $stats['sent']);
        $broadcast = Broadcast::find($id);
        $this->assertSame([Broadcast::QUEUED, 1, 1], [$broadcast->status, $broadcast->recipients_queued, $broadcast->recipients_skipped]);
        $this->assertSame($a, MessageSend::where('broadcast_id', $id)->sole()->student_id);
        $this->assertSame('New mocks for Medical', MessageSend::sole()->subject);
        $this->assertSame(1, $this->getJson('/api/admin/marketing/broadcasts')->json('data.0.stats.sent'));
    }

    public function test_broadcast_waits_while_paused_and_outside_the_window(): void
    {
        $this->students(1);
        MarketingSettings::set(MarketingSettings::PAUSED, true);
        $broadcast = Broadcast::create(['name' => 'x', 'template_key' => 'news', 'filters' => ['exam_type_id' => 1], 'scheduled_for' => now()]);
        (new AutomationEngine())->dispatch();
        $this->assertSame(Broadcast::SCHEDULED, $broadcast->fresh()->status);

        MarketingSettings::set(MarketingSettings::PAUSED, false);
        $this->travelTo(CarbonImmutable::parse('2026-09-26 22:00', 'Asia/Kathmandu'));
        (new AutomationEngine())->dispatch();
        $this->assertSame(Broadcast::QUEUED, $broadcast->fresh()->status);
        $this->assertEquals(CarbonImmutable::parse('2026-09-27 07:00', 'Asia/Kathmandu'), MessageSend::sole()->scheduled_for);
        Mail::assertNothingSent();
    }

    public function test_broadcasts_obey_the_frequency_cap_and_can_be_cancelled(): void
    {
        [$recent, $fresh] = $this->students(2);
        MessageSend::create(['student_id' => $recent, 'template_key' => 'x', 'category' => 'lifecycle', 'status' => 'sent', 'sent_at' => now()->subDay()]);

        $this->travelTo(CarbonImmutable::parse('2026-09-26 21:00', 'Asia/Kathmandu'));
        $id = $this->postJson('/api/admin/marketing/broadcasts', ['name' => 'b', 'template_key' => 'news', 'filters' => ['exam_type_id' => 1]])->json('data.id');
        (new AutomationEngine())->dispatch(); // releases, but window is closed

        $this->assertSame([1, 1], [Broadcast::find($id)->recipients_queued, Broadcast::find($id)->recipients_skipped]); // $recent is capped
        $this->assertSame([$fresh], MessageSend::where('broadcast_id', $id)->pluck('student_id')->all());

        $this->postJson("/api/admin/marketing/broadcasts/{$id}/cancel")->assertOk();
        $this->assertSame('broadcast_cancelled', MessageSend::where('broadcast_id', $id)->value('suppress_reason'));
        $this->travelTo(CarbonImmutable::parse('2026-09-27 07:30', 'Asia/Kathmandu'));
        (new AutomationEngine())->dispatch();
        Mail::assertNothingSent();
    }

    public function test_higher_priority_automation_beats_a_broadcast(): void
    {
        [$id] = $this->students(1);
        EmailTemplate::create(['key' => 'checkout', 'name' => 'c', 'subject' => 'c', 'html_body' => '<p>c</p>']);
        Automation::create(['key' => 'checkout', 'name' => 'c', 'trigger_type' => 'scheduled', 'template_key' => 'checkout', 'priority' => 98, 'is_active' => true]);
        (new AutomationEngine())->plan();
        Broadcast::create(['name' => 'b', 'template_key' => 'news', 'filters' => ['exam_type_id' => 1], 'scheduled_for' => now()]);

        (new AutomationEngine())->dispatch();

        $this->assertSame('checkout', MessageSend::where('status', 'sent')->sole()->template_key);
        $this->assertSame(0, MessageSend::whereNotNull('broadcast_id')->count(), 'broadcast recipient skipped at release (slot reserved by the automation)');
    }

    // ------------------------------------------------------------ templates

    public function test_template_crud_and_guards(): void
    {
        $payload = ['key' => 'promo_exam', 'name' => 'Promo', 'category' => 'promotional', 'subject' => 'Hi {{first_name}}', 'html_body' => '<p>x</p>', 'cta_path' => '/student/subscription'];
        $this->postJson('/api/admin/marketing/templates', $payload)->assertCreated();
        $this->postJson('/api/admin/marketing/templates', $payload)->assertStatus(422); // duplicate key
        $this->postJson('/api/admin/marketing/templates', ['key' => 'Bad Key'] + $payload)->assertStatus(422);
        $this->postJson('/api/admin/marketing/templates', ['key' => 'ext', 'cta_path' => 'https://evil.example'] + $payload)->assertStatus(422);

        $this->putJson('/api/admin/marketing/templates/promo_exam', ['key' => 'renamed', 'subject' => 'Updated'] + $payload)->assertOk();
        $this->assertSame('Updated', EmailTemplate::where('key', 'promo_exam')->value('subject'));

        Automation::create(['key' => 'uses', 'name' => 'u', 'trigger_type' => 'scheduled', 'template_key' => 'promo_exam']);
        $this->deleteJson('/api/admin/marketing/templates/promo_exam')->assertStatus(422);
        $this->deleteJson('/api/admin/marketing/templates/news')->assertOk();

        $id = $this->student(['name' => 'Kiran Thapa']);
        $this->postJson('/api/admin/marketing/templates/draft-preview', ['subject' => 'Draft for {{first_name}}', 'html_body' => '<p>x</p>', 'student_id' => $id])
            ->assertOk()->assertJsonPath('data.subject', 'Draft for Kiran');
        $this->assertSame(0, EmailTemplate::where('key', 'draft')->count());

        $list = $this->getJson('/api/admin/marketing/templates')->assertOk()->json('data');
        $this->assertContains('first_name', $list['variables']);
        $this->assertSame(['uses'], collect($list['templates'])->firstWhere('key', 'promo_exam')['used_by']);
    }

    // ------------------------------------------------------------ A/B

    public function test_ab_significance(): void
    {
        $this->assertTrue(AbTestAnalyzer::compare(50, 10, 50, 2)['needs_more_data']);
        $this->assertSame('B', AbTestAnalyzer::compare(400, 40, 400, 70)['winner']);
        $this->assertSame('A', AbTestAnalyzer::compare(400, 70, 400, 40)['winner']);
        $this->assertNull(AbTestAnalyzer::compare(400, 40, 400, 45)['winner']);
    }

    public function test_ab_setup_and_promote_winner(): void
    {
        EmailTemplate::create(['key' => 'news_b', 'name' => 'News B', 'subject' => 'B', 'html_body' => '<p>b</p>']);
        $auto = Automation::create(['key' => 'n', 'name' => 'n', 'trigger_type' => 'scheduled', 'template_key' => 'news']);

        $this->patchJson("/api/admin/marketing/automations/{$auto->id}", ['variant_b_template_key' => 'news'])->assertStatus(422);
        $this->patchJson("/api/admin/marketing/automations/{$auto->id}", ['variant_b_template_key' => 'news_b', 'ab_split_pct' => 50])->assertOk();
        $this->assertNotNull($this->getJson('/api/admin/marketing/automations')->json('data.automations.0.ab.variants.B'));

        $this->postJson("/api/admin/marketing/automations/{$auto->id}/promote", ['variant' => 'B'])->assertOk();
        $auto->refresh();
        $this->assertSame(['news_b', null], [$auto->template_key, $auto->variant_b_template_key]);
        $this->postJson("/api/admin/marketing/automations/{$auto->id}/promote", ['variant' => 'A'])->assertStatus(422);
    }
}
