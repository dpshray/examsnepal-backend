<?php

namespace App\Services\Notices\Adapters;

use App\Models\NoticeSource;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Generic list-page scraper driven by the source's `selectors` JSON:
 *
 *   item         CSS selector for one notice (a card, <li> or table <tr>)   required
 *   title        selector inside item for the title text (default: link)
 *   link         selector inside item for the link element (default: "a")
 *   link_attr    attribute holding the URL (default: href; e.g. data-href)
 *   date         selector inside item for the date text (optional)
 *   date_attr    read the date from this attribute instead of the text
 *                (for dates rendered client-side, e.g. englishdate="2026-09-15")
 *   date_url_regex  regex with named groups y/m/d applied to the item URL
 *                when the list shows no (or a wrong) date, e.g.
 *                "--(?<y>\d{2})(?<m>\d{2})(?<d>\d{2})" for /detail/--260828102346
 *   attachments  selector inside item for file links (default: a[href$=".pdf"])
 *   skip_title   regex; items whose title matches are ignored (e.g. tenders)
 *   limit        max items to keep (default 40) - first page only
 */
class HtmlListAdapter extends AbstractAdapter
{
    public function fetchList(NoticeSource $source): array
    {
        $html = $this->http->get($source->list_url, $source)->body();

        return $this->parse($html, $source);
    }

    /** Separated from fetching so fixtures can be parsed in tests / "Test fetch". */
    public function parse(string $html, NoticeSource $source): array
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($html, 'UTF-8');

        $items = $crawler->filter($source->selector('item', 'table tbody tr'));
        $this->snapshotHash = $this->structureHash($crawler, $items);

        $linkSelector = $source->selector('link', 'a');
        $linkAttr = $source->selector('link_attr', 'href');
        $titleSelector = $source->selector('title');
        $dateSelector = $source->selector('date');
        $dateAttr = $source->selector('date_attr');
        $attachmentSelector = $source->selector('attachments', 'a[href$=".pdf"], a[href$=".PDF"]');
        $skip = $source->selector('skip_title');
        $limit = (int) $source->selector('limit', 40);

        $result = [];
        $items->each(function (Crawler $item) use (&$result, $source, $linkSelector, $linkAttr, $titleSelector, $dateSelector, $dateAttr, $attachmentSelector, $skip, $limit) {
            if (count($result) >= $limit) {
                return;
            }

            $link = $linkSelector === '@self' ? $item : $item->filter($linkSelector)->first();
            $href = $link->count() ? (string) $link->attr($linkAttr) : '';

            $titleNode = $titleSelector ? $item->filter($titleSelector)->first() : $link;
            $title = $titleNode->count() ? $this->clean($titleNode->text('', true)) : '';

            if ($title === '' || $href === '' || str_starts_with($href, 'javascript:') || $href === '#') {
                return;
            }
            if ($skip && preg_match('/'.$skip.'/iu', $title)) {
                return;
            }

            $date = null;
            if ($dateSelector) {
                $dateNode = $item->filter($dateSelector)->first();
                $date = $dateNode->count()
                    ? $this->clean($dateAttr ? (string) $dateNode->attr($dateAttr) : $dateNode->text('', true))
                    : null;
            }

            $url = $this->absoluteUrl($href, $source->list_url);

            if (! $date && ($urlRegex = $source->selector('date_url_regex')) && preg_match('#'.$urlRegex.'#', $url, $m)) {
                $year = strlen($m['y']) === 2 ? '20'.$m['y'] : $m['y'];
                $date = sprintf('%s-%02d-%02d', $year, $m['m'], $m['d']);
            }
            $attachments = [];
            $item->filter($attachmentSelector)->each(function (Crawler $a) use (&$attachments, $source) {
                $attachmentUrl = $this->absoluteUrl((string) $a->attr('href'), $source->list_url);
                if ($attachmentUrl !== '') {
                    $attachments[$attachmentUrl] = ['url' => $attachmentUrl, 'name' => $this->clean($a->text('', true)) ?: null];
                }
            });
            if ($this->isAttachment($url)) {
                $attachments[$url] ??= ['url' => $url, 'name' => null];
            }

            $result[] = new RawNoticeItem(
                title: $title,
                url: $url,
                dateText: $date ?: null,
                attachments: array_values($attachments),
            );
        });

        return $result;
    }

    private function structureHash(Crawler $page, Crawler $items): string
    {
        // Class names of the item containers + page <title>: stable while
        // content changes daily, different after a redesign.
        $classes = $items->count() ? (string) $items->first()->attr('class') : '';
        $title = $page->filter('title')->count() ? $page->filter('title')->text('') : '';

        return hash('sha256', $items->count() > 0 ? 'items:'.$classes : 'none:'.$title);
    }
}
