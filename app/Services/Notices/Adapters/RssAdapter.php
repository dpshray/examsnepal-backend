<?php

namespace App\Services\Notices\Adapters;

use App\Models\NoticeSource;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * RSS 2.0 / Atom feeds (e.g. WordPress /category/x/feed/).
 * selectors (optional): skip_title regex, limit.
 */
class RssAdapter extends AbstractAdapter
{
    public function fetchList(NoticeSource $source): array
    {
        return $this->parse($this->http->get($source->list_url, $source)->body(), $source);
    }

    public function parse(string $xml, NoticeSource $source): array
    {
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);

        if ($feed === false) {
            throw new RuntimeException('Response is not a valid RSS/Atom feed');
        }

        $entries = isset($feed->channel) ? $feed->channel->item : $feed->entry;
        $this->snapshotHash = hash('sha256', $feed->getName());
        $skip = $source->selector('skip_title');
        $limit = (int) $source->selector('limit', 40);

        $result = [];
        foreach ($entries as $entry) {
            if (count($result) >= $limit) {
                break;
            }

            $title = $this->clean((string) $entry->title);
            $link = (string) ($entry->link['href'] ?? $entry->link);
            if ($title === '' || $link === '' || ($skip && preg_match('/'.$skip.'/iu', $title))) {
                continue;
            }

            $attachments = [];
            foreach ($entry->enclosure as $enclosure) {
                $attachments[] = ['url' => (string) $enclosure['url'], 'name' => null];
            }

            $date = (string) ($entry->pubDate ?: $entry->published ?: $entry->updated);
            $publishedAd = null;
            try {
                $publishedAd = $date ? CarbonImmutable::parse($date)->setTimezone('Asia/Kathmandu')->toDateString() : null;
            } catch (Throwable) {
            }

            $result[] = new RawNoticeItem(
                title: $title,
                url: $this->absoluteUrl($link, $source->base_url),
                dateText: $date ?: null,
                attachments: $attachments,
                publishedAd: $publishedAd,
            );
        }

        return $result;
    }
}
