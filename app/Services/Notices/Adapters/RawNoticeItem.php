<?php

namespace App\Services\Notices\Adapters;

/**
 * One entry as parsed from a source's list page, before normalization.
 */
final class RawNoticeItem
{
    /**
     * @param  array<int, array{url: string, name?: string|null}>  $attachments
     */
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $dateText = null,
        public readonly array $attachments = [],
        public readonly ?string $titleAlt = null,
        public readonly ?string $publishedAd = null,
    ) {}

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'title_alt' => $this->titleAlt,
            'url' => $this->url,
            'date_text' => $this->dateText,
            'published_ad' => $this->publishedAd,
            'attachments' => $this->attachments,
        ];
    }
}
