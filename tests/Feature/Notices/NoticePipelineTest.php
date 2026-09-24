<?php

namespace Tests\Feature\Notices;

use App\Jobs\EnrichNoticeJob;
use App\Models\Notice;
use App\Models\NoticeFetchLog;
use App\Services\Notices\NoticeFetcher;
use Illuminate\Support\Facades\Http;
use Tests\Unit\Notices\NoticeExtractionSchemaTest;

class NoticePipelineTest extends NoticeDatabaseTestCase
{
    private function fakeLumbini(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        Http::fake([
            'ppsc.lumbini.gov.np/robots.txt' => Http::response('', 404),
            'ppsc.lumbini.gov.np/notices' => Http::response(file_get_contents(base_path('tests/fixtures/notices/ppsc-lumbini-notice.html'))),
            'ppsc.lumbini.gov.np/notice/*' => Http::response('<html><body><main><h1>सूचना</h1><p>दरखास्त दिने अन्तिम मिति २०८३/०६/२१ गते।</p></main></body></html>'),
            'ppsc.lumbini.gov.np/media/*' => Http::response('%PDF-1.4 not really a pdf', 200, ['Content-Type' => 'application/pdf']),
        ]);
    }

    public function test_full_pipeline_fetch_ingest_enrich(): void
    {
        $this->fakeLumbini();
        config(['notices.autopublish_enabled' => false]);
        $source = $this->makeSource();

        $this->ai->responses = array_fill(0, 20, NoticeExtractionSchemaTest::validPayload([
            'title_en' => 'Lumbini PSC Officer Exam Schedule 2083',
            'notice_type' => 'exam_schedule',
            'province' => 'lumbini',
            'exam_date_bs' => '2083-07-15',
            'application_deadline_bs' => '2083-06-21',
            'exam_tags' => ['section-officer', 'made-up-tag'],
        ]));

        $log = app(NoticeFetcher::class)->run($source);

        $this->assertSame('success', $log->status);
        $this->assertSame(12, $log->items_found);
        // Fixture holds 12 items; with "today" = 2026-09-24 and a 45-day
        // cutoff, the 9 published on/after 2083-04-26 are new.
        $this->assertSame(9, $log->items_new);
        $this->assertSame(9, Notice::count());

        $notice = Notice::where('source_url', 'https://ppsc.lumbini.gov.np/notice/218')->firstOrFail();
        $this->assertSame('2083-06-02', $notice->published_date_bs);
        $this->assertSame('2026-09-18', $notice->published_date_ad->toDateString());

        // Enrichment ran (sync queue): BS dates converted server-side,
        // unknown tags dropped, slug rebuilt from the English title.
        $this->assertNotNull($notice->enriched_at);
        $this->assertSame('exam_schedule', $notice->notice_type);
        $this->assertSame('2026-10-07', $notice->application_deadline_ad->toDateString());
        $this->assertSame('2026-11-01', $notice->exam_date_ad->toDateString()); // cross-checked with ernilambar/nepali-date
        $this->assertSame('2083-07-15', $notice->exam_date_bs);
        $this->assertSame(['section-officer'], $notice->exam_tags);
        $this->assertStringStartsWith('lumbini-psc-officer-exam-schedule-2083', $notice->slug);
        $this->assertSame('pending', $notice->status, 'autopublish is off');
        // Its attachment has no text layer -> treated as scanned -> stronger model.
        $this->assertSame('claude-sonnet-5', $notice->ai_model);
        $this->assertSame(1200, $notice->ai_input_tokens);

        // The fake "PDF" has no text layer, so it was sent as a document block.
        $firstCall = $this->ai->calls[0]['content'];
        $this->assertContains('document', array_column($firstCall, 'type'));
    }

    public function test_second_run_inserts_nothing_and_health_is_tracked(): void
    {
        $this->fakeLumbini();
        $source = $this->makeSource();
        $fetcher = app(NoticeFetcher::class);

        $fetcher->run($source);
        $second = $fetcher->run($source->fresh());

        $this->assertSame(0, $second->items_new);
        $this->assertSame(2, NoticeFetchLog::count());
        $this->assertNotNull($source->fresh()->last_success_at);
        $this->assertSame(0, $source->fresh()->consecutive_failures);
    }

    public function test_failures_are_logged_and_counted(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        $source = $this->makeSource();

        $log = app(NoticeFetcher::class)->run($source);

        $this->assertSame('failed', $log->status);
        $this->assertSame(1, $source->fresh()->consecutive_failures);
        $this->assertNotNull($source->fresh()->last_error);
    }

    public function test_empty_runs_mark_source_unhealthy(): void
    {
        Http::fake(['*/robots.txt' => Http::response('', 404), '*' => Http::response('<html><body>maintenance</body></html>')]);
        $source = $this->makeSource();

        foreach (range(1, 3) as $_) {
            app(NoticeFetcher::class)->run($source->fresh());
        }

        $this->assertSame(3, $source->fresh()->consecutive_empty_runs);
        $this->assertFalse($source->fresh()->isHealthy());
    }

    public function test_robots_disallow_is_respected(): void
    {
        Http::fake(['*/robots.txt' => Http::response("User-agent: *\nDisallow: /"), '*' => Http::response('should not be fetched')]);

        $log = app(NoticeFetcher::class)->run($this->makeSource());

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('robots.txt', $log->error);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/notices'));
    }

    public function test_tenders_are_rejected_without_ai(): void
    {
        $this->travelTo('2026-09-24');
        Http::fake([
            '*/robots.txt' => Http::response('', 404),
            'ppsc.gandaki.gov.np/list/notice_bord' => Http::response(file_get_contents(base_path('tests/fixtures/notices/ppsc-gandaki-notice.html'))),
            '*' => Http::response('<main>detail</main>'),
        ]);
        $source = $this->makeSource([
            'list_url' => 'https://ppsc.gandaki.gov.np/list/notice_bord', 'base_url' => 'https://ppsc.gandaki.gov.np',
            'selectors' => ['item' => 'li.tap-box-list', 'link' => 'a:not(.dn-link)', 'title' => 'a:not(.dn-link) p', 'date' => 'span.nepaliDate', 'date_attr' => 'englishdate'],
        ]);

        app(NoticeFetcher::class)->run($source);

        $tender = Notice::where('title_original', 'like', '%बोलपत्र%')->firstOrFail();
        $this->assertSame('rejected', $tender->status);
        $this->assertNull($tender->enriched_at);
        $this->assertCount(Notice::where('status', '!=', 'rejected')->count(), $this->ai->calls);
    }

    public function test_autopublish_rules(): void
    {
        // No readable attachments -> every notice takes the text path
        // (primary model first), which keeps the response order predictable.
        Http::fake(['ppsc.lumbini.gov.np/media/*' => Http::response('', 404)]);
        $this->fakeLumbini();
        config(['notices.autopublish_enabled' => true]);
        $source = $this->makeSource();

        $this->ai->responses = [
            NoticeExtractionSchemaTest::validPayload(['confidence' => 0.95]),                     // published
            NoticeExtractionSchemaTest::validPayload(['is_relevant' => false, 'confidence' => 0.99]), // rejected
            NoticeExtractionSchemaTest::validPayload(['confidence' => 0.7]),                      // pending (below 0.8)
            NoticeExtractionSchemaTest::validPayload(['confidence' => 0.3]),                      // triggers fallback model
            NoticeExtractionSchemaTest::validPayload(['confidence' => 0.85]),                     // fallback result -> published
        ];

        app(NoticeFetcher::class)->run($source);
        $ordered = Notice::orderBy('id')->get();

        $this->assertSame('published', $ordered[0]->status);
        $this->assertNotNull($ordered[0]->published_at);
        $this->assertSame('rejected', $ordered[1]->status);
        $this->assertSame('pending', $ordered[2]->status);
        $this->assertSame('published', $ordered[3]->status);
        $this->assertSame('claude-sonnet-5', $ordered[3]->ai_model);
    }

    public function test_untrusted_source_never_autopublishes(): void
    {
        $this->fakeLumbini();
        config(['notices.autopublish_enabled' => true]);

        app(NoticeFetcher::class)->run($this->makeSource(['is_trusted' => false]));

        $this->assertSame(0, Notice::where('status', 'published')->count());
    }

    public function test_invalid_ai_output_is_retried_once_then_left_pending(): void
    {
        $this->fakeLumbini();
        $bad = ['nonsense' => true];
        $this->ai->responses = [$bad, $bad, $bad, $bad];

        $source = $this->makeSource();
        $notice = Notice::create([
            'source_id' => $source->id, 'category' => 'loksewa', 'organization' => 'X',
            'title_original' => 'सूचना', 'source_url' => 'https://ppsc.lumbini.gov.np/notice/1', 'content_hash' => str_repeat('a', 64),
        ]);

        (new EnrichNoticeJob($notice->id))->handle(app(\App\Services\Notices\Enrichment\NoticeEnricher::class), app(\App\Services\Notices\NoticePublisher::class));

        $notice->refresh();
        $this->assertSame('pending', $notice->status);
        $this->assertNull($notice->enriched_at);
        $this->assertNotNull($notice->enrichment_error);
        // primary + one retry + fallback model
        $this->assertCount(3, $this->ai->calls);
    }

    public function test_same_document_is_never_sent_twice(): void
    {
        $this->fakeLumbini();
        $source = $this->makeSource();
        $make = fn ($hash) => Notice::create([
            'source_id' => $source->id, 'category' => 'loksewa', 'organization' => 'X',
            'title_original' => 'Same title', 'source_url' => 'https://ppsc.lumbini.gov.np/notice/1', 'content_hash' => $hash,
        ]);

        EnrichNoticeJob::dispatchSync($make(str_repeat('a', 64))->id);
        EnrichNoticeJob::dispatchSync($make(str_repeat('b', 64))->id);

        $this->assertCount(1, $this->ai->calls);
        $this->assertSame(2, Notice::whereNotNull('enriched_at')->count());
    }

    public function test_fuzzy_duplicate_with_new_url_is_skipped(): void
    {
        $this->fakeLumbini();
        $source = $this->makeSource();
        app(NoticeFetcher::class)->run($source);
        $existing = Notice::first();

        $result = app(\App\Services\Notices\NoticeIngestor::class)->ingest($source, [
            new \App\Services\Notices\Adapters\RawNoticeItem($existing->title_original.' ।', 'https://ppsc.lumbini.gov.np/notice/9999', '२०८३-०६-०२'),
        ]);

        $this->assertCount(0, $result['new']);
        $this->assertStringContainsString('similar to notice', $result['skipped'][0]['reason']);
    }

    public function test_daily_request_limit_defers_enrichment_until_tomorrow(): void
    {
        $this->fakeLumbini();
        config(['notices.ai.daily_request_limit' => 2, 'notices.ai.fallback_model' => 'claude-haiku-4-5']);
        $source = $this->makeSource();
        $make = fn ($i) => Notice::create([
            'source_id' => $source->id, 'category' => 'loksewa', 'organization' => 'X',
            'title_original' => "Notice {$i}", 'source_url' => "https://ppsc.lumbini.gov.np/notice/{$i}", 'content_hash' => str_repeat((string) $i, 64),
        ]);

        foreach ([1, 2, 3] as $i) {
            $job = (new EnrichNoticeJob($make($i)->id))->withFakeQueueInteractions();
            $job->handle(app(\App\Services\Notices\Enrichment\NoticeEnricher::class), app(\App\Services\Notices\NoticePublisher::class));
            $jobs[$i] = $job;
        }

        $this->assertCount(2, $this->ai->calls, 'third request blocked by the cap');
        $jobs[1]->assertNotReleased();
        $jobs[3]->assertReleased();
        $this->assertNull(Notice::where('title_original', 'Notice 3')->first()->enrichment_error, 'waiting is not an error');
        $this->assertGreaterThan(now()->addDays(6), $jobs[3]->retryUntil());
    }

    public function test_without_ai_trusted_sources_publish_as_fetched(): void
    {
        $this->fakeLumbini();
        config(['notices.ai.provider' => 'none', 'notices.autopublish_enabled' => true]);

        app(NoticeFetcher::class)->run($this->makeSource());
        app(NoticeFetcher::class)->run($this->makeSource([
            'name' => 'Mixed news board', 'is_trusted' => false,
            'list_url' => 'https://ppsc.lumbini.gov.np/notices?copy=1',
        ]));

        $this->assertCount(0, $this->ai->calls, 'no AI calls');
        $trusted = Notice::whereHas('source', fn ($q) => $q->where('is_trusted', true))->get();
        $this->assertSame(9, $trusted->where('status', 'published')->count());
        $notice = $trusted->firstWhere('source_url', 'https://ppsc.lumbini.gov.np/notice/218');
        $this->assertSame('2083-06-02', $notice->published_date_bs);
        $this->assertStringStartsWith('सूचना नं.', $notice->title_original);
        $this->assertNotNull($notice->published_at);

        $this->assertSame(0, Notice::whereHas('source', fn ($q) => $q->where('is_trusted', false))->where('status', 'published')->count(), 'untrusted sources wait for review');
    }

    public function test_queued_ai_jobs_publish_directly_once_ai_is_turned_off(): void
    {
        config(['notices.ai.provider' => 'none', 'notices.autopublish_enabled' => true]);
        $source = $this->makeSource();
        $notice = Notice::create([
            'source_id' => $source->id, 'category' => 'loksewa', 'organization' => 'X',
            'title_original' => 'सूचना', 'source_url' => 'https://ppsc.lumbini.gov.np/notice/1', 'content_hash' => str_repeat('c', 64),
        ]);

        EnrichNoticeJob::dispatchSync($notice->id);

        $this->assertSame('published', $notice->fresh()->status);
        $this->assertCount(0, $this->ai->calls);
    }
}
