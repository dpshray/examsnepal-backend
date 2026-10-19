<?php

namespace Tests\Feature\Notices;

use App\Jobs\EnrichNoticeJob;
use App\Models\Notice;
use App\Services\Notices\Enrichment\NoticeEnricher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Unit\Notices\NoticeExtractionSchemaTest;

class NoticeBackfillTest extends NoticeDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The official page has nothing beyond the title.
        Http::fake(['*' => Http::response('<html><body></body></html>')]);
    }

    private function published(array $attributes = []): Notice
    {
        return Notice::create(array_replace([
            'category' => 'loksewa',
            'organization' => 'Public Service Commission',
            'title_original' => 'प्रथम श्रेणी विज्ञापन',
            'slug' => 'psc-notice-live',
            'source_url' => 'https://psc.gov.np/notice/'.Str::random(6),
            'content_hash' => hash('sha256', Str::random()),
            'notice_type' => 'other',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    public function test_confident_extraction_fills_a_live_notice_without_changing_its_url(): void
    {
        $notice = $this->published();
        $this->ai->responses[] = NoticeExtractionSchemaTest::validPayload(['confidence' => 0.9]);

        app(NoticeEnricher::class)->enrich($notice);

        $notice->refresh();
        $this->assertNotNull($notice->enriched_at);
        $this->assertSame('The Public Service Commission has advertised first-class posts.', $notice->summary_en);
        $this->assertNotNull($notice->application_deadline_ad);
        $this->assertSame('psc-notice-live', $notice->slug);
        $this->assertSame('published', $notice->status);
    }

    public function test_low_confidence_extraction_is_kept_for_review_not_shown_on_the_live_notice(): void
    {
        $notice = $this->published();
        // Primary model, then the fallback model it escalates to below 0.6 - both unsure.
        $this->ai->responses = [
            NoticeExtractionSchemaTest::validPayload(['confidence' => 0.4]),
            NoticeExtractionSchemaTest::validPayload(['confidence' => 0.4]),
        ];

        app(NoticeEnricher::class)->enrich($notice);

        $notice->refresh();
        $this->assertNull($notice->summary_en);
        $this->assertNull($notice->application_deadline_ad);
        $this->assertNull($notice->enriched_at);
        $this->assertSame(0.4, $notice->ai_confidence);
        $this->assertStringContainsString('not applied to the live notice', $notice->enrichment_error);
    }

    public function test_backfill_queues_only_unenriched_published_notices(): void
    {
        Queue::fake();
        $todo = $this->published();
        $this->published(['enriched_at' => now(), 'slug' => 'done']);
        $this->published(['ai_raw' => ['confidence' => 0.3], 'slug' => 'reviewed']); // kept for review earlier
        $this->published(['status' => 'pending', 'published_at' => null, 'slug' => 'pending']);

        $this->artisan('notices:enrich-published')->assertSuccessful();

        Queue::assertPushed(EnrichNoticeJob::class, 1);
        Queue::assertPushed(EnrichNoticeJob::class, fn ($job) => $job->noticeId === $todo->id);
    }

    public function test_backfill_refuses_when_ai_is_off(): void
    {
        config(['notices.ai.provider' => 'none']);

        $this->artisan('notices:enrich-published')->assertFailed();
    }
}
