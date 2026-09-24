<?php

namespace App\Services\Notices;

use App\Jobs\EnrichNoticeJob;
use App\Mail\Notices\SourceUnhealthyMail;
use App\Models\NoticeFetchLog;
use App\Models\NoticeSource;
use App\Services\Notices\Adapters\AdapterFactory;
use App\Services\Notices\Enrichment\NoticeAiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NoticeFetcher
{
    public function __construct(
        private readonly AdapterFactory $adapters,
        private readonly NoticeIngestor $ingestor,
        private readonly NoticePublisher $publisher,
    ) {}

    /**
     * Parse a source without saving anything (CLI --dry-run, admin "Test fetch").
     */
    public function preview(NoticeSource $source): array
    {
        $items = $this->adapters->for($source)->fetchList($source);
        $result = $this->ingestor->ingest($source, $items, dryRun: true);
        $newHashes = collect($result['new'])->pluck('content_hash')->flip();

        return array_map(function ($item) use ($source, $newHashes) {
            $prepared = $this->ingestor->prepare($source, $item);

            return $item->toArray() + [
                'published_date_bs' => $prepared['published_date_bs'],
                'published_date_ad' => $prepared['published_date_ad'],
                'would_insert' => $newHashes->has($prepared['content_hash']),
            ];
        }, $items);
    }

    public function run(NoticeSource $source): NoticeFetchLog
    {
        $log = NoticeFetchLog::create(['source_id' => $source->id, 'started_at' => now(), 'status' => 'running']);
        $source->forceFill(['last_fetched_at' => now()])->save();

        try {
            $adapter = $this->adapters->for($source);
            $items = $adapter->fetchList($source);
            $result = $this->ingestor->ingest($source, $items);

            $snapshot = $adapter->lastSnapshotHash();
            $empty = count($items) === 0;

            $source->forceFill([
                'last_success_at' => now(),
                'last_item_count' => count($items),
                'consecutive_failures' => 0,
                'consecutive_empty_runs' => $empty ? $source->consecutive_empty_runs + 1 : 0,
                'last_error' => $empty && $snapshot && $source->last_snapshot_hash && $snapshot !== $source->last_snapshot_hash
                    ? 'Page structure changed and no items were found - site redesign?'
                    : ($empty ? 'No items found' : null),
                'last_snapshot_hash' => $snapshot ?? $source->last_snapshot_hash,
            ])->save();

            $log->update([
                'finished_at' => now(),
                'items_found' => count($items),
                'items_new' => count($result['new']),
                'status' => $empty ? 'empty' : 'success',
            ]);

            foreach (array_filter($result['new'], fn ($n) => $n->status === 'pending') as $notice) {
                if (NoticeAiClient::isEnabled()) {
                    EnrichNoticeJob::dispatch($notice->id);
                } else {
                    $this->publisher->publishWithoutAi($notice->load('source'));
                }
            }
        } catch (Throwable $e) {
            $source->forceFill([
                'consecutive_failures' => $source->consecutive_failures + 1,
                'last_error' => mb_substr(class_basename($e).': '.$e->getMessage(), 0, 2000),
            ])->save();

            $log->update(['finished_at' => now(), 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 5000)]);
            Log::warning('notices: fetch failed', ['source_id' => $source->id, 'error' => $e->getMessage()]);
        }

        $this->alertIfUnhealthy($source->fresh());

        return $log->fresh();
    }

    private function alertIfUnhealthy(NoticeSource $source): void
    {
        if ($source->isHealthy()) {
            if ($source->unhealthy_notified_at) {
                $source->forceFill(['unhealthy_notified_at' => null])->save();
            }

            return;
        }

        // One alert per unhealthy streak, not one per run.
        if ($source->unhealthy_notified_at || ! config('notices.alert_email')) {
            return;
        }

        try {
            Mail::to(config('notices.alert_email'))->send(new SourceUnhealthyMail($source));
            $source->forceFill(['unhealthy_notified_at' => now()])->save();
        } catch (Throwable $e) {
            Log::error('notices: could not send unhealthy-source alert', ['source_id' => $source->id, 'error' => $e->getMessage()]);
        }
    }
}
