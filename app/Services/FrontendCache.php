<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the Vercel frontend which cached pages are out of date.
 *
 * Public pages are cached for days to keep Vercel costs down, so content edits
 * queue a cache tag here and `frontend:flush-cache` (every minute) sends the
 * batch to the frontend's /api/revalidate. Tags: "public-data" (menus,
 * categories, guides, blog lists), "notices", "mcq" (every MCQ page) and
 * "mcq:{slug}" (one MCQ page).
 */
class FrontendCache
{
    private const PENDING_KEY = 'frontend-cache:pending-tags';

    /** Above this many single-question tags, flush every MCQ page in one go. */
    private const MAX_QUESTION_TAGS = 200;

    public static function invalidate(string ...$tags): void
    {
        if (! self::enabled()) {
            return;
        }

        try {
            Cache::lock(self::PENDING_KEY . ':lock', 5)->block(3, function () use ($tags) {
                $pending = Cache::get(self::PENDING_KEY, []);
                Cache::forever(self::PENDING_KEY, array_values(array_unique([...$pending, ...$tags])));
            });
        } catch (Throwable $e) {
            // A missed flush only leaves a page stale until its cache expires.
            Log::warning('Could not queue frontend cache invalidation', ['tags' => $tags, 'error' => $e->getMessage()]);
        }
    }

    public static function questionChanged(?string $slug): void
    {
        if ($slug) {
            self::invalidate('mcq:' . $slug);
        }
    }

    /** Sends queued tags to the frontend. Returns how many tags were sent. */
    public static function flush(): int
    {
        if (! self::enabled()) {
            return 0;
        }

        $tags = Cache::lock(self::PENDING_KEY . ':lock', 5)->block(3, fn () => Cache::pull(self::PENDING_KEY, []));
        if (! $tags) {
            return 0;
        }

        $questionTags = array_filter($tags, fn ($t) => str_starts_with($t, 'mcq:'));
        if (count($questionTags) > self::MAX_QUESTION_TAGS) {
            $tags = [...array_diff($tags, $questionTags), 'mcq'];
        }

        try {
            foreach (array_chunk(array_values($tags), 100) as $chunk) {
                Http::timeout(15)
                    ->withHeaders(['x-revalidate-secret' => config('services.frontend.revalidate_secret')])
                    ->post(config('services.frontend.revalidate_url'), ['tags' => $chunk])
                    ->throw();
            }
        } catch (Throwable $e) {
            self::invalidate(...$tags); // retry on the next run
            throw $e;
        }

        return count($tags);
    }

    private static function enabled(): bool
    {
        return (bool) config('services.frontend.revalidate_url') && (bool) config('services.frontend.revalidate_secret');
    }
}
