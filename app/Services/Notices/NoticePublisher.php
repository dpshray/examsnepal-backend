<?php

namespace App\Services\Notices;

use App\Jobs\NotifyNoticeSubscribersJob;
use App\Models\Notice;
use Illuminate\Support\Facades\Cache;

/**
 * Publishing rules (Phase 4) and everything that has to happen when the set
 * of public notices changes.
 */
class NoticePublisher
{
    /** Decide the status of a freshly enriched notice. */
    public function afterEnrichment(Notice $notice): void
    {
        if ($notice->status !== 'pending') {
            return;
        }

        $raw = $notice->ai_raw;
        if (! $notice->enriched_at || ! is_array($raw)) {
            return; // enrichment failed - stays in the review queue
        }

        if (($raw['is_relevant'] ?? true) === false) {
            $notice->update(['status' => 'rejected']);

            return;
        }

        if ($this->canAutoPublish($notice)) {
            $this->publish($notice);
        }
    }

    /**
     * Without AI: publish straight from the fetch (title as published, list
     * date, official link) for trusted sources. Untrusted sources - boards
     * that mix in general news - wait in the admin review queue.
     */
    public function publishWithoutAi(Notice $notice): void
    {
        if ($notice->status === 'pending'
            && config('notices.autopublish_enabled')
            && ($notice->source?->is_trusted ?? false)
            && trim($notice->title_original) !== ''
            && filter_var($notice->source_url, FILTER_VALIDATE_URL)) {
            $this->publish($notice);
        }
    }

    public function canAutoPublish(Notice $notice): bool
    {
        return config('notices.autopublish_enabled')
            && ($notice->ai_raw['is_relevant'] ?? false) === true
            && $notice->ai_confidence >= (float) config('notices.ai.autopublish_min_confidence')
            && ($notice->source?->is_trusted ?? false)
            && filter_var($notice->source_url, FILTER_VALIDATE_URL)
            && trim((string) $notice->title_en) !== '';
    }

    public function publish(Notice $notice): void
    {
        $wasPublished = $notice->getOriginal('status') === 'published' || $notice->published_at;

        $notice->status = 'published';
        $notice->save();
        $this->flushCaches();

        if (! $wasPublished && config('notices.notifications_enabled')) {
            NotifyNoticeSubscribersJob::dispatch($notice->id)->onQueue(config('notices.queue'));
        }
    }

    /**
     * Public list/detail responses are cached under a version number; bumping
     * it invalidates them all without needing a taggable cache store.
     */
    public function flushCaches(): void
    {
        Cache::forever('notices:cache-version', (int) Cache::get('notices:cache-version', 1) + 1);
    }

    public static function cacheKey(string $suffix): string
    {
        return 'notices:v'.Cache::get('notices:cache-version', 1).':'.$suffix;
    }
}
