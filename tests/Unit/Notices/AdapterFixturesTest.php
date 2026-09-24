<?php

namespace Tests\Unit\Notices;

use App\Models\NoticeSource;
use App\Services\Notices\Adapters\AdapterFactory;
use App\Services\Notices\NoticeIngestor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Parses the saved list page of every seeded, fetchable source
 * (tests/fixtures/notices/{code}.*) with its configured adapter.
 * When a site redesign breaks a source, refresh its fixture and this
 * test shows exactly which selectors need updating.
 */
class AdapterFixturesTest extends TestCase
{
    public static function sources(): array
    {
        $cases = [];
        foreach (require __DIR__.'/../../../database/seeders/data/notice_sources.php' as $code => $attributes) {
            if (($attributes['fetch_type'] ?? 'manual') === 'manual') {
                continue;
            }
            $ext = ['json_api' => 'json', 'rss' => 'xml'][$attributes['fetch_type']] ?? 'html';
            $cases[$code] = [$code, $attributes, __DIR__."/../../fixtures/notices/{$code}.{$ext}"];
        }

        return $cases;
    }

    #[DataProvider('sources')]
    public function test_fixture_parses(string $code, array $attributes, string $fixture): void
    {
        $this->assertFileExists($fixture, "Missing fixture for {$code}");

        $source = new NoticeSource($attributes);
        $source->id = 1;
        $items = app(AdapterFactory::class)->for($source)->parse(file_get_contents($fixture), $source);

        $this->assertNotEmpty($items, "{$code}: no items parsed");

        $ingestor = app(NoticeIngestor::class);
        $dated = 0;
        foreach ($items as $item) {
            $this->assertNotSame('', $item->title, "{$code}: empty title");
            $this->assertMatchesRegularExpression('#^https?://#', $item->url, "{$code}: relative url {$item->url}");
            $this->assertStringNotContainsString(' ', $item->url, "{$code}: unencoded url");
            $prepared = $ingestor->prepare($source, $item);
            if ($prepared['published_date_ad']) {
                $dated++;
                $this->assertMatchesRegularExpression('/^20[0-9]{2}-\d\d-\d\d$/', $prepared['published_date_ad']);
                $this->assertLessThanOrEqual(now()->addDay()->toDateString(), $prepared['published_date_ad'], "{$code}: date in the future");
            }
        }

        // Every source whose list shows dates must yield a date for each item.
        $undatedSources = ['ku-news', 'rbb-career'];
        if (! in_array($code, $undatedSources, true)) {
            $this->assertSame(count($items), $dated, "{$code}: some items have no parsed date");
        }
    }
}
