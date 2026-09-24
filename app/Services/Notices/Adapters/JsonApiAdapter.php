<?php

namespace App\Services\Notices\Adapters;

use App\Models\NoticeSource;

/**
 * For sites that render their notice board from a JSON endpoint.
 *
 * selectors:
 *   items_path   dot path to the item array            e.g. data.children.data
 *   title        field holding the title                e.g. title_np
 *   title_alt    optional second-language title          e.g. title
 *   date         field with the display date (BS or AD) e.g. upload_date_bs
 *   date_ad      optional field with an AD date          e.g. upload_date
 *   url          field holding the notice URL, or
 *   url_template e.g. https://psc.gov.np/category/notice/{slug}
 *   attachments  {path: files, url: location, name: name}
 */
class JsonApiAdapter extends AbstractAdapter
{
    public function fetchList(NoticeSource $source): array
    {
        $response = $this->http->get($source->list_url, $source, [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        return $this->parse($response->body(), $source);
    }

    public function parse(string $body, NoticeSource $source): array
    {
        $json = json_decode($body, true);
        $items = data_get($json, $source->selector('items_path'), []);
        $this->snapshotHash = hash('sha256', json_encode(array_keys((array) ($items[0] ?? []))));

        $result = [];
        foreach ((array) $items as $item) {
            $title = $this->clean(data_get($item, $source->selector('title')));
            $alt = $source->selector('title_alt') ? $this->clean(data_get($item, $source->selector('title_alt'))) : null;
            if ($title === '' && $alt) {
                [$title, $alt] = [$alt, null];
            }

            $url = $source->selector('url')
                ? (string) data_get($item, $source->selector('url'))
                : preg_replace_callback('/\{(\w+)\}/', fn ($m) => rawurlencode((string) data_get($item, $m[1])), (string) $source->selector('url_template'));

            if ($title === '' || $url === '') {
                continue;
            }

            $attachments = [];
            if ($attachmentConfig = $source->selector('attachments')) {
                foreach ((array) data_get($item, $attachmentConfig['path'] ?? 'files', []) as $file) {
                    $fileUrl = (string) data_get($file, $attachmentConfig['url'] ?? 'url');
                    if ($fileUrl !== '' && $fileUrl !== '#') {
                        $attachments[] = [
                            'url' => $this->absoluteUrl($fileUrl, $source->base_url),
                            'name' => data_get($file, $attachmentConfig['name'] ?? 'name'),
                        ];
                    }
                }
            }

            $result[] = new RawNoticeItem(
                title: $title,
                url: $this->absoluteUrl($url, $source->base_url),
                dateText: $source->selector('date') ? (string) data_get($item, $source->selector('date')) : null,
                attachments: $attachments,
                titleAlt: $alt ?: null,
                publishedAd: $source->selector('date_ad') ? (string) data_get($item, $source->selector('date_ad')) : null,
            );
        }

        return $result;
    }
}
