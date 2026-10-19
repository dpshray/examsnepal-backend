<?php

namespace App\Services\Notices;

use App\Models\Notice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Posts a published notice to the Facebook Page as a photo post. The image is
 * rendered by the admin panel (the browser shapes Devanagari correctly, which
 * server-side image libraries here don't) and uploaded with the publish
 * request; it's kept on disk so a failed post can be retried.
 */
class FacebookNoticePoster
{
    private const HASHTAGS = [
        'loksewa' => '#Loksewa #LokSewaAayog',
        'entrance' => '#EntranceExam',
        'license' => '#LicenseExam',
    ];

    public static function configured(): bool
    {
        return (bool) config('notices.facebook.page_id') && (bool) config('notices.facebook.page_token');
    }

    public function noticeUrl(Notice $notice): string
    {
        return config('notices.site_url').'/notices/'.$notice->slug;
    }

    public function defaultCaption(Notice $notice): string
    {
        $lines = [$notice->displayTitle(), ''];
        $lines[] = '🏛 '.$notice->organization;
        if ($notice->published_date_bs) {
            $lines[] = '📅 Published: '.$notice->published_date_bs.' BS';
        }
        if ($notice->application_deadline_ad) {
            $lines[] = '⏰ Apply by: '.$notice->application_deadline_ad->format('j M Y');
        }
        if ($notice->exam_date_bs || $notice->exam_date_ad) {
            $lines[] = '📝 Exam: '.($notice->exam_date_bs ? $notice->exam_date_bs.' BS' : $notice->exam_date_ad->format('j M Y'));
        }
        $lines[] = '';
        $lines[] = 'Full details & official notice 👉 '.$this->noticeUrl($notice);
        $lines[] = '';
        $lines[] = trim('#ExamsNepal '.(self::HASHTAGS[$notice->category] ?? '').' #NepalExams');

        return implode("\n", $lines);
    }

    public function storeImage(Notice $notice, string $contents): void
    {
        Storage::disk('local')->put($this->imagePath($notice), $contents);
    }

    public function hasStoredImage(Notice $notice): bool
    {
        return Storage::disk('local')->exists($this->imagePath($notice));
    }

    /**
     * Upload the stored image with $caption. Records the outcome on the notice
     * either way and never throws - the website publish must not fail because
     * of Facebook. Returns true on success.
     */
    public function post(Notice $notice, string $caption): bool
    {
        try {
            if (! self::configured()) {
                throw new RuntimeException('Facebook Page is not configured (NOTICES_FACEBOOK_PAGE_ID / NOTICES_FACEBOOK_PAGE_TOKEN).');
            }
            if ($notice->facebook_post_id) {
                throw new RuntimeException('This notice is already on Facebook.');
            }
            if (! $this->hasStoredImage($notice)) {
                throw new RuntimeException('No image to post - publish again from the admin panel to render one.');
            }

            $response = Http::timeout(60)
                ->attach('source', Storage::disk('local')->get($this->imagePath($notice)), "notice-{$notice->id}.png")
                ->post(sprintf(
                    'https://graph.facebook.com/%s/%s/photos',
                    config('notices.facebook.graph_version'),
                    config('notices.facebook.page_id'),
                ), [
                    'message' => $caption,
                    'access_token' => config('notices.facebook.page_token'),
                ]);

            if ($response->failed() || ! $response->json('id')) {
                throw new RuntimeException('Facebook: '.($response->json('error.message') ?? "HTTP {$response->status()}"));
            }

            $notice->forceFill([
                'facebook_post_id' => $response->json('post_id') ?? $response->json('id'),
                'facebook_posted_at' => now(),
                'facebook_error' => null,
            ])->save();

            return true;
        } catch (Throwable $e) {
            if (! $e instanceof RuntimeException) {
                report($e); // network/unexpected - the expected cases above are just shown to the admin
            }
            $notice->forceFill(['facebook_error' => mb_substr($e->getMessage(), 0, 500)])->save();

            return false;
        }
    }

    public static function postUrl(?string $postId): ?string
    {
        return $postId ? 'https://www.facebook.com/'.$postId : null;
    }

    private function imagePath(Notice $notice): string
    {
        return "notices/facebook/{$notice->id}.png";
    }
}
