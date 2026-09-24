<?php

namespace App\Services\Notices\Adapters;

use App\Services\Notices\Http\NoticeHttpClient;
use App\Services\Notices\Support\TitleNormalizer;

abstract class AbstractAdapter implements NoticeSourceAdapter
{
    protected ?string $snapshotHash = null;

    public function __construct(protected readonly NoticeHttpClient $http) {}

    public function lastSnapshotHash(): ?string
    {
        return $this->snapshotHash;
    }

    protected function absoluteUrl(string $href, string $base): string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $href)) {
            return $this->encodePath($href);
        }
        if (str_starts_with($href, '//')) {
            return $this->encodePath((parse_url($base, PHP_URL_SCHEME) ?: 'https').':'.$href);
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($href, '/')) {
            return $this->encodePath($origin.$href);
        }

        $dir = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/');

        return $this->encodePath($origin.$this->resolveDots($dir.$href));
    }

    /** Keep the URL valid when sites link to paths with spaces/Devanagari. */
    protected function encodePath(string $url): string
    {
        return preg_replace_callback('#[^\x21-\x7E]#u', fn ($m) => rawurlencode($m[0]), $url);
    }

    protected function clean(?string $text): string
    {
        return TitleNormalizer::clean((string) $text);
    }

    protected function isAttachment(string $url): bool
    {
        return (bool) preg_match('#\.(pdf|jpe?g|png|webp|docx?|xlsx?)(\?|$)#i', $url);
    }

    private function resolveDots(string $path): string
    {
        $out = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($out);
            } elseif ($segment !== '.') {
                $out[] = $segment;
            }
        }

        return implode('/', $out);
    }
}
