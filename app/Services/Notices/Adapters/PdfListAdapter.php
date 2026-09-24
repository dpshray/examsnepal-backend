<?php

namespace App\Services\Notices\Adapters;

use App\Models\NoticeSource;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Pages that are just a list of PDF links. Every PDF link inside
 * `selectors.container` (default: body) becomes one notice whose URL is
 * the PDF itself. Title = link text, else the file name.
 */
class PdfListAdapter extends AbstractAdapter
{
    public function fetchList(NoticeSource $source): array
    {
        return $this->parse($this->http->get($source->list_url, $source)->body(), $source);
    }

    public function parse(string $html, NoticeSource $source): array
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($html, 'UTF-8');
        $container = $crawler->filter($source->selector('container', 'body'));
        $links = $container->filter('a[href$=".pdf"], a[href$=".PDF"], a[href*=".pdf?"]');
        $this->snapshotHash = hash('sha256', 'pdf:'.($links->count() > 0 ? 'y' : 'n'));
        $limit = (int) $source->selector('limit', 40);
        $skip = $source->selector('skip_title');

        $result = [];
        $links->each(function (Crawler $a) use (&$result, $source, $limit, $skip) {
            if (count($result) >= $limit) {
                return;
            }
            $url = $this->absoluteUrl((string) $a->attr('href'), $source->list_url);
            $title = $this->clean($a->text('', true));
            if ($title === '' || preg_match('/^(download|view|pdf|click here|डाउनलोड|हेर्नुहोस्)$/iu', $title)) {
                $title = $this->clean(str_replace(['-', '_'], ' ', pathinfo(rawurldecode(parse_url($url, PHP_URL_PATH)), PATHINFO_FILENAME)));
            }
            if ($title === '' || isset($result[$url]) || ($skip && preg_match('/'.$skip.'/iu', $title))) {
                return;
            }

            $result[$url] = new RawNoticeItem(title: $title, url: $url, attachments: [['url' => $url, 'name' => null]]);
        });

        return array_values($result);
    }
}
