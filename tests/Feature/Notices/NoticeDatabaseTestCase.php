<?php

namespace Tests\Feature\Notices;

use App\Models\NoticeSource;
use App\Services\Notices\Enrichment\NoticeAiClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * phpunit.xml points at the developer's MySQL database, so notice tests
 * switch to an in-memory sqlite connection and run only the migrations they
 * need - they never touch real data.
 */
abstract class NoticeDatabaseTestCase extends TestCase
{
    protected FakeNoticeAiClient $ai;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'notices_testing',
            'database.connections.notices_testing' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'notices.queue_connection' => 'sync',
            'notices.http.per_host_delay_ms' => 0,
            'notices.http.retries' => 1,
            'notices.ai.provider' => 'openrouter',
            'notices.ai.openrouter.api_key' => 'test-key',
            'notices.ai.model' => 'claude-haiku-4-5',
            'notices.ai.fallback_model' => 'claude-sonnet-5',
            'notices.ai.daily_request_limit' => 0,
        ]);
        DB::purge('notices_testing');

        foreach ([
            '2025_01_29_204543_create_student_profiles_table',
            '2026_08_08_225333_create_exam_categories_table',
            '2026_08_08_225334_create_exam_guides_table',
            '2026_09_04_234903_add_type_and_official_source_to_exam_guides_table',
            '2026_09_24_100000_create_notice_sources_table',
            '2026_09_24_100100_create_notices_table',
            '2026_09_24_100200_create_notice_fetch_logs_table',
            '2026_09_24_100300_create_notice_subscriptions_table',
            '2026_09_24_100400_create_notice_reports_table',
        ] as $migration) {
            Artisan::call('migrate', ['--path' => "database/migrations/{$migration}.php", '--database' => 'notices_testing', '--force' => true]);
        }

        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run notice tests on a non-sqlite DB');

        $this->ai = new FakeNoticeAiClient();
        $this->app->instance(NoticeAiClient::class, $this->ai);
    }

    protected function makeSource(array $overrides = []): NoticeSource
    {
        return NoticeSource::create(array_replace([
            'name' => 'Lumbini Province PSC — Notices',
            'organization' => 'Lumbini Province Public Service Commission',
            'category' => 'loksewa',
            'sub_category' => 'provincial_psc',
            'province' => 'lumbini',
            'base_url' => 'https://ppsc.lumbini.gov.np',
            'list_url' => 'https://ppsc.lumbini.gov.np/notices',
            'fetch_type' => 'html_list',
            'selectors' => ['item' => 'tbody.table-hover-css tr', 'link' => 'a.list-detail-font', 'date' => 'td:first-child', 'attachments' => 'a[href*=".pdf"]'],
        ], $overrides));
    }
}

/** Stand-in for the Claude API: returns queued payloads and records calls. */
class FakeNoticeAiClient extends NoticeAiClient
{
    public array $calls = [];

    public array $responses = [];

    public function extract(string $model, string $system, array $content, array $schema): array
    {
        $this->calls[] = compact('model', 'content');
        $data = array_shift($this->responses) ?? \Tests\Unit\Notices\NoticeExtractionSchemaTest::validPayload();

        return ['data' => $data, 'raw' => json_encode($data), 'input_tokens' => 1200, 'output_tokens' => 300, 'cache_read_tokens' => 0, 'stop_reason' => 'end_turn'];
    }
}
