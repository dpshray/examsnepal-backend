<?php

namespace App\Services\Notices;

use App\Models\Notice;
use App\Models\NoticeSource;
use App\Services\Notices\Adapters\RawNoticeItem;
use App\Services\Notices\Support\NepaliDate;
use App\Services\Notices\Support\NoticeTypeClassifier;
use App\Services\Notices\Support\TitleNormalizer;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns adapter output into `pending` notices: normalizes, hashes, drops
 * exact and fuzzy duplicates, and fills the dates it can read directly.
 */
class NoticeIngestor
{
    /**
     * @param  RawNoticeItem[]  $items
     * @return array{new: Notice[], skipped: array<int, array{title: string, reason: string}>}
     */
    public function ingest(NoticeSource $source, array $items, bool $dryRun = false): array
    {
        $new = [];
        $skipped = [];
        $seenHashes = [];
        $cutoff = today()->subDays((int) config('notices.max_item_age_days'));

        foreach ($items as $item) {
            $prepared = $this->prepare($source, $item);
            $hash = $prepared['content_hash'];

            // Titles that are only punctuation (e.g. "-" next to an image) clean to "".
            if (mb_strlen($prepared['title_original']) < 3) {
                $skipped[] = ['title' => $item->title, 'reason' => 'no usable title'];

                continue;
            }

            if (isset($seenHashes[$hash]) || Notice::where('content_hash', $hash)->exists()) {
                $skipped[] = ['title' => $item->title, 'reason' => 'exists'];

                continue;
            }
            $seenHashes[$hash] = true;

            if ($prepared['published_date_ad'] && CarbonImmutable::parse($prepared['published_date_ad'])->lt($cutoff)) {
                $skipped[] = ['title' => $item->title, 'reason' => 'too old'];

                continue;
            }

            if ($duplicate = $this->fuzzyDuplicate($source, $prepared['title_original'])) {
                $skipped[] = ['title' => $item->title, 'reason' => "similar to notice #{$duplicate->id}"];

                continue;
            }

            if (preg_match('/'.config('notices.irrelevant_title_regex').'/iu', $prepared['title_original'].' '.$prepared['title_en'])) {
                $prepared['status'] = 'rejected';
                $prepared['enrichment_error'] = 'Auto-rejected: tender/procurement notice (title filter)';
            }

            $notice = new Notice($prepared);
            if (! $dryRun) {
                $notice->save();
            }
            $new[] = $notice;
        }

        return ['new' => $new, 'skipped' => $skipped];
    }

    public function prepare(NoticeSource $source, RawNoticeItem $item): array
    {
        [$publishedAd, $publishedBs] = $this->publishedDate($item, $source->selector('date_format'));
        $title = TitleNormalizer::clean($item->title);
        $isNepali = (bool) preg_match('/\p{Devanagari}/u', $title);
        $alt = $item->titleAlt ? TitleNormalizer::clean($item->titleAlt) : null;

        return [
            'source_id' => $source->id,
            'category' => $source->category,
            'sub_category' => $source->sub_category,
            'organization' => $source->organization,
            'province' => $source->province,
            'title_original' => $title,
            // Sites that publish both languages (PSC) give us these for free;
            // the enrichment step fills whichever is missing.
            'title_ne' => $isNepali ? $title : null,
            'title_en' => ! $isNepali ? $title : ($alt && ! preg_match('/\p{Devanagari}/u', $alt) ? $alt : null),
            'source_url' => $item->url,
            'attachment_urls' => $item->attachments ?: null,
            'published_date_ad' => $publishedAd,
            'published_date_bs' => $publishedBs,
            'notice_type' => NoticeTypeClassifier::classify($title, (string) $alt),
            'content_hash' => TitleNormalizer::contentHash($source->id, $title, $item->url),
            'status' => 'pending',
        ];
    }

    /** @return array{0: ?string, 1: ?string} [AD Y-m-d, BS Y-m-d] */
    private function publishedDate(RawNoticeItem $item, ?string $adFormat = null): array
    {
        // Ambiguous AD formats (08/06/2026) are declared per source.
        if ($adFormat && $item->dateText) {
            try {
                $ad = CarbonImmutable::createFromFormat('!'.$adFormat, trim($item->dateText))->toDateString();

                return [$ad, NepaliDate::adToBs($ad)];
            } catch (Throwable) {
            }
        }

        if ($bs = NepaliDate::normalizeBs($item->dateText)) {
            $ad = NepaliDate::bsToAd($bs)?->toDateString();

            return [$ad, $bs];
        }

        $adText = preg_replace('/\s+,/', ',', (string) ($item->publishedAd ?: $item->dateText));
        if ($adText && preg_match('/\b(19|20[0-5])\d[-\/]\d{1,2}[-\/]\d{1,2}(?!\d)|[a-z]{3,}/i', $adText)) {
            try {
                // Timestamps (RSS pubDate, ISO createdAt) are usually UTC;
                // the notice date is the date in Nepal.
                $ad = CarbonImmutable::parse($adText)->setTimezone('Asia/Kathmandu')->toDateString();

                return [$ad, NepaliDate::adToBs($ad)];
            } catch (Throwable) {
            }
        }

        return [null, null];
    }

    private function fuzzyDuplicate(NoticeSource $source, string $title): ?Notice
    {
        $threshold = (float) config('notices.dedup.fuzzy_similarity');

        return Notice::query()
            ->where('organization', $source->organization)
            ->where('created_at', '>=', now()->subDays((int) config('notices.dedup.fuzzy_window_days')))
            ->latest('id')
            ->limit(200)
            ->get(['id', 'title_original'])
            ->first(fn (Notice $n) => TitleNormalizer::similarity($n->title_original, $title) >= $threshold);
    }
}
