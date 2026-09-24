<?php

namespace App\Jobs;

use App\Models\Notice;
use App\Services\Notices\Enrichment\AiDailyLimitReachedException;
use App\Services\Notices\Enrichment\NoticeEnricher;
use App\Services\Notices\NoticePublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichNoticeJob implements ShouldQueue
{
    use Queueable;

    // Waiting for the daily quota counts as an attempt, so bound by time
    // instead of attempt count.
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDays(7);
    }

    public int $maxExceptions = 4;

    public int $timeout = 240;

    public function __construct(public int $noticeId, public bool $force = false)
    {
        $this->onConnection(config('notices.queue_connection'));
        $this->onQueue(config('notices.queue'));
    }

    /** Retries are for transient API errors (429 / 5xx / network). */
    public function backoff(): array
    {
        return [60, 300, 1200];
    }

    public function handle(NoticeEnricher $enricher, NoticePublisher $publisher): void
    {
        $notice = Notice::with('source')->find($this->noticeId);
        if (! $notice) {
            return;
        }

        // AI switched off after this job was queued: publish as fetched.
        if (! \App\Services\Notices\Enrichment\NoticeAiClient::isEnabled()) {
            $publisher->publishWithoutAi($notice);

            return;
        }

        try {
            $enricher->enrich($notice, $this->force);
        } catch (AiDailyLimitReachedException) {
            // Not a failure: put it back until the daily quota resets.
            $this->release(max(60, (int) now('UTC')->diffInSeconds(now('UTC')->endOfDay()) + 120));

            return;
        }

        $publisher->afterEnrichment($notice->fresh('source'));
    }
}
