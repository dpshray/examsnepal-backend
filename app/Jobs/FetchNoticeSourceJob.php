<?php

namespace App\Jobs;

use App\Models\NoticeSource;
use App\Services\Notices\NoticeFetcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchNoticeSourceJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    // Retries happen inside NoticeHttpClient; a failed run is simply logged
    // and picked up again at the next interval.
    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 900;

    public function __construct(public int $sourceId)
    {
        $this->onConnection(config('notices.queue_connection'));
        $this->onQueue(config('notices.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->sourceId;
    }

    public function handle(NoticeFetcher $fetcher): void
    {
        $source = NoticeSource::find($this->sourceId);

        if ($source && $source->is_active && $source->fetch_type !== 'manual') {
            $fetcher->run($source);
        }
    }
}
