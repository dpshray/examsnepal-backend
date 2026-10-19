<?php

namespace Tests\Feature\Notices;

use App\Models\Notice;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NoticeFacebookTest extends NoticeDatabaseTestCase
{
    private const GRAPH = 'https://graph.facebook.com/v23.0/123456/photos';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Auth\Middleware\Authenticate::class, \App\Http\Middleware\CheckUserRole::class, \App\Http\Middleware\AuthenticateApi::class]);
        Storage::fake('local');
        config([
            'notices.facebook.graph_version' => 'v23.0',
            'notices.facebook.page_id' => '123456',
            'notices.facebook.page_token' => 'page-token',
            'notices.notifications_enabled' => false,
        ]);
    }

    private function notice(array $attributes = []): Notice
    {
        return Notice::create(array_replace([
            'category' => 'loksewa',
            'organization' => 'Public Service Commission',
            'title_original' => 'खरिदार पदको विज्ञापन सूचना',
            'source_url' => 'https://psc.gov.np/notice/1',
            'content_hash' => hash('sha256', Str::random()),
            'published_date_bs' => '2083-07-03',
            'notice_type' => 'vacancy',
            'status' => 'pending',
        ], $attributes));
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('card.png', 1080, 1080);
    }

    public function test_publish_posts_the_image_to_the_page_and_records_the_post(): void
    {
        Http::fake([self::GRAPH => Http::response(['id' => '999', 'post_id' => '123456_999'])]);
        $notice = $this->notice();

        $this->post("/api/admin/notices/{$notice->id}/approve", [
            'post_to_facebook' => '1',
            'facebook_caption' => 'Custom caption',
            'facebook_image' => $this->image(),
        ])->assertOk()
            ->assertJsonPath('message', 'Notice published on the website and Facebook')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.facebook.post_url', 'https://www.facebook.com/123456_999');

        Http::assertSent(fn (Request $r) => $r->url() === self::GRAPH
            && collect($r->data())->contains(fn ($part) => ($part['name'] ?? null) === 'source')
            && collect($r->data())->contains(fn ($part) => ($part['name'] ?? null) === 'message' && $part['contents'] === 'Custom caption'));
        $this->assertSame('123456_999', $notice->fresh()->facebook_post_id);
    }

    public function test_facebook_failure_keeps_the_website_publish_and_can_be_retried(): void
    {
        Http::fakeSequence(self::GRAPH)
            ->push(['error' => ['message' => '(#200) The permission(s) pages_manage_posts are not available.']], 403)
            ->push(['id' => '1', 'post_id' => '123456_1']);
        $notice = $this->notice();

        $this->post("/api/admin/notices/{$notice->id}/approve", ['post_to_facebook' => '1', 'facebook_image' => $this->image()])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.facebook.error', 'Facebook: (#200) The permission(s) pages_manage_posts are not available.');

        // Retry reuses the stored image - no re-upload needed.
        $this->post("/api/admin/notices/{$notice->id}/facebook")
            ->assertOk()
            ->assertJsonPath('data.facebook.post_id', '123456_1')
            ->assertJsonPath('data.facebook.error', null);

        // Never posted twice.
        $this->post("/api/admin/notices/{$notice->id}/facebook")->assertStatus(409);
        Http::assertSentCount(2);
    }

    public function test_publish_without_facebook_does_not_call_the_graph_api(): void
    {
        Http::fake();
        $notice = $this->notice();

        $this->post("/api/admin/notices/{$notice->id}/approve")->assertOk()->assertJsonPath('message', 'Notice published');

        Http::assertNothingSent();
        $this->assertNull($notice->fresh()->facebook_error);
    }

    public function test_unconfigured_page_reports_why_without_calling_facebook(): void
    {
        Http::fake();
        config(['notices.facebook.page_token' => null]);
        $notice = $this->notice();

        $this->post("/api/admin/notices/{$notice->id}/approve", ['post_to_facebook' => '1', 'facebook_image' => $this->image()])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.facebook.configured', false);

        Http::assertNothingSent();
        $this->assertStringContainsString('not configured', $notice->fresh()->facebook_error);
    }

    public function test_default_caption_has_title_organization_dates_and_link(): void
    {
        $notice = $this->notice(['slug' => 'kharidar-vacancy', 'application_deadline_ad' => '2026-11-15']);

        $caption = $this->getJson("/api/admin/notices/{$notice->id}")->assertOk()->json('data.facebook.default_caption');

        $this->assertStringStartsWith('खरिदार पदको विज्ञापन सूचना', $caption);
        $this->assertStringContainsString('Public Service Commission', $caption);
        $this->assertStringContainsString('Published: 2083-07-03 BS', $caption);
        $this->assertStringContainsString('Apply by: 15 Nov 2026', $caption);
        $this->assertStringContainsString('https://www.examsnepal.com/notices/kharidar-vacancy', $caption);
        $this->assertStringContainsString('#Loksewa', $caption);
    }

    public function test_unpublished_notices_cannot_be_posted_directly(): void
    {
        $notice = $this->notice();

        $this->post("/api/admin/notices/{$notice->id}/facebook", ['facebook_image' => $this->image()])->assertStatus(422);
    }
}
