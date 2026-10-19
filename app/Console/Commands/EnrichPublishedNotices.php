<?php

namespace App\Console\Commands;

use App\Jobs\EnrichNoticeJob;
use App\Models\Notice;
use App\Services\Notices\Enrichment\NoticeAiClient;
use Illuminate\Console\Command;

class EnrichPublishedNotices extends Command
{
    protected $signature = 'notices:enrich-published
        {--limit=100 : Most notices to queue in this run (newest first)}
        {--dry-run : List what would be queued without queuing anything}';

    protected $description = 'Queue AI enrichment (summary, dates, posts, fees) for published notices that were published before AI was switched on';

    public function handle(): int
    {
        if (! NoticeAiClient::isEnabled() || ! NoticeAiClient::isConfigured()) {
            $this->error('AI enrichment is off or has no key - set NOTICES_AI_PROVIDER and its API key first.');

            return self::FAILURE;
        }

        // Low-confidence results on live notices are kept for review (see
        // NoticeEnricher::apply) and leave enriched_at NULL - skip those so a
        // re-run doesn't keep re-queuing them.
        $notices = Notice::query()
            ->where('status', 'published')
            ->whereNull('enriched_at')
            ->whereNull('ai_raw')
            ->orderByDesc('published_at')
            ->limit((int) $this->option('limit'))
            ->get(['id', 'organization', 'title_original']);

        foreach ($notices as $notice) {
            $this->line("#{$notice->id} {$notice->organization}: ".mb_strimwidth($notice->title_original, 0, 70, '…'));
            if (! $this->option('dry-run')) {
                EnrichNoticeJob::dispatch($notice->id);
            }
        }

        $this->info(sprintf(
            '%s %d notice(s). Jobs run on the "%s" queue (cron worker) within the daily AI limit.',
            $this->option('dry-run') ? 'Would queue' : 'Queued',
            $notices->count(),
            config('notices.queue'),
        ));

        return self::SUCCESS;
    }
}
