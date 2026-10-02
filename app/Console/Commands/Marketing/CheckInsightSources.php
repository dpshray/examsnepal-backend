<?php

namespace App\Console\Commands\Marketing;

use App\Services\Marketing\Insights\InsightsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CheckInsightSources extends Command
{
    protected $signature = 'marketing:insights-check {--days=28 : Period to fetch}';

    protected $description = 'Test the Search Console, GA4 and Facebook connections behind the insights page';

    public function handle(): int
    {
        $to = CarbonImmutable::today();
        $service = new InsightsService($to->subDays((int) $this->option('days') - 1), $to);
        $ok = true;

        foreach (array_keys(InsightsService::SOURCES) as $source) {
            $result = $service->source($source, refresh: true);
            if (! $result['configured']) {
                $this->warn("$source: not configured");
                foreach ($result['setup'] as $step) {
                    $this->line("  - $step");
                }
                continue;
            }
            if ($result['error']) {
                $ok = false;
                $this->error("$source: {$result['error']}");
                foreach ($result['setup'] as $step) {
                    $this->line("  - $step");
                }
                continue;
            }
            $this->info(sprintf('%s: OK, %d insight(s)', $source, count($result['data']['insights'])));
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
