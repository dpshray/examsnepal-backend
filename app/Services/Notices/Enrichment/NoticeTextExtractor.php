<?php

namespace App\Services\Notices\Enrichment;

use App\Models\Notice;
use App\Services\Notices\Http\NoticeHttpClient;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * Collects the notice content to send to the model:
 *   1. the detail page's main text (unless the URL is itself a file)
 *   2. each PDF's text layer - or the PDF itself when the layer is empty or
 *      legacy-font garbage (Preeti/Kantipur encodings are common on .gov.np
 *      and extract as Latin gibberish)
 *   3. images are always sent as images (scanned notices)
 */
class NoticeTextExtractor
{
    private const MAX_FILES = 3;

    /** Only the first pages of a PDF are read; notices put the facts up front. */
    private const TEXT_PAGES = 5;

    /** PDFs longer than this (candidate lists, results) are never sent to the model. */
    private const MAX_PDF_PAGES = 15;

    /** smalot/pdfparser loads the whole file into memory - only for small PDFs. */
    private const MAX_PHP_PARSE_BYTES = 1_500_000;

    public function __construct(private readonly NoticeHttpClient $http) {}

    public function extract(Notice $notice): NoticeDocument
    {
        $texts = [];
        $files = [];
        $warnings = [];
        $attachmentUrls = collect($notice->attachment_urls ?? [])->pluck('url')->filter()->values()->all();

        if (! $this->isFileUrl($notice->source_url)) {
            try {
                [$pageText, $pageFiles] = $this->pageText($notice);
                if ($pageText !== '') {
                    $texts[] = "=== Notice page ({$notice->source_url}) ===\n".$pageText;
                }
                $attachmentUrls = array_values(array_unique([...$attachmentUrls, ...$pageFiles]));
            } catch (Throwable $e) {
                $warnings[] = 'detail page: '.$e->getMessage();
            }
        } else {
            array_unshift($attachmentUrls, $notice->source_url);
            $attachmentUrls = array_values(array_unique($attachmentUrls));
        }

        // The same file often appears both URL-encoded and not.
        $attachmentUrls = collect($attachmentUrls)
            ->map(fn ($u) => str_replace(' ', '%20', rawurldecode($u)))
            ->unique()
            ->values()
            ->all();

        foreach (array_slice($attachmentUrls, 0, self::MAX_FILES) as $url) {
            try {
                [$bytes, $type] = $this->http->download($url, $notice->source);
                $type = $this->sniffType($bytes, $type, $url);

                if ($type === 'application/pdf') {
                    $pdfText = $this->pdfText($bytes);
                    $pages = $this->pageCount($bytes);
                    if ($this->isUsableText($pdfText, $notice)) {
                        $texts[] = "=== Attachment ({$url}) ===\n".$pdfText;
                    } elseif ($pages > self::MAX_PDF_PAGES) {
                        // e.g. a 173-page approved-candidates list - the notice
                        // facts are in the title/page, not worth sending.
                        $warnings[] = "PDF has {$pages} pages, not sent: {$url}";
                    } elseif (strlen($bytes) <= config('notices.ai.max_pdf_bytes')) {
                        $files[] = ['media_type' => 'application/pdf', 'data' => $bytes, 'url' => $url];
                    } else {
                        $warnings[] = "PDF too large to send: {$url}";
                    }
                } elseif (in_array($type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                    $files[] = ['media_type' => $type, 'data' => $bytes, 'url' => $url];
                }
            } catch (Throwable $e) {
                $warnings[] = "attachment {$url}: ".$e->getMessage();
            }
        }

        $text = Str::limit(implode("\n\n", $texts), (int) config('notices.ai.max_text_chars'), "\n[...truncated]");

        return new NoticeDocument($text, $files, $warnings);
    }

    /** @return array{0: string, 1: string[]} main text + file links found on the page */
    private function pageText(Notice $notice): array
    {
        $html = $this->http->get($notice->source_url, $notice->source)->body();

        // JSON endpoints (e.g. a source whose detail URL is an API) - use as is.
        if (str_starts_with(ltrim($html), '{')) {
            return [Str::limit(strip_tags($html), 8000, ''), []];
        }

        $crawler = new Crawler();
        $crawler->addHtmlContent($html, 'UTF-8');
        $crawler->filter('script, style, nav, header, footer, noscript, form, .navbar, .menu, .sidebar, .footer, .header')->each(
            fn (Crawler $node) => $node->getNode(0)?->parentNode?->removeChild($node->getNode(0))
        );

        $files = [];
        $crawler->filter('a[href], iframe[src], embed[src], img[src]')->each(function (Crawler $node) use (&$files, $notice) {
            $href = $node->attr('href') ?? $node->attr('src') ?? '';
            if (preg_match('#\.(pdf|jpe?g|png|webp)(\?|$)#i', $href) && ! preg_match('#(logo|icon|banner|flag|avatar)#i', $href)) {
                $files[] = $this->absolute($href, $notice->source_url);
            }
        });

        $main = $crawler->filter('main, article, .content, .detail, .notice-detail, #content, .container')->first();
        $text = ($main->count() ? $main : $crawler->filter('body'))->text('', true);

        return [trim(preg_replace('/\s{3,}/u', "\n\n", $text)), array_values(array_unique($files))];
    }

    private function pdfText(string $bytes): string
    {
        $text = $this->pdftotext($bytes);

        if ($text === null) {
            if (strlen($bytes) > self::MAX_PHP_PARSE_BYTES) {
                return ''; // too big to parse safely in PHP memory
            }
            try {
                $text = (new PdfParser())->parseContent($bytes)->getText();
            } catch (Throwable) {
                return '';
            }
        }

        return trim(preg_replace('/[ \t]+/u', ' ', $text));
    }

    /**
     * poppler's pdftotext (first pages only, constant memory) when the server
     * has it; null when unavailable so the PHP parser is used instead.
     */
    private function pdftotext(string $bytes): ?string
    {
        static $binary = null;
        $binary ??= $this->findBinary('pdftotext');
        if (! $binary || ! function_exists('proc_open')) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'notice-pdf-');
        file_put_contents($tmp, $bytes);
        try {
            $result = \Illuminate\Support\Facades\Process::timeout(30)
                ->run([$binary, '-l', (string) self::TEXT_PAGES, '-enc', 'UTF-8', $tmp, '-']);

            return $result->successful() ? $result->output() : null;
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }

    private function findBinary(string $name): string|false
    {
        foreach (['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin'] as $dir) {
            if (is_executable("{$dir}/{$name}")) {
                return "{$dir}/{$name}";
            }
        }

        return false;
    }

    /** Cheap page count from the raw PDF (no parsing). */
    private function pageCount(string $bytes): int
    {
        return (int) preg_match_all('#/Type\s*/Page(?!s)#', $bytes);
    }

    /**
     * A Nepali notice whose text layer has almost no Devanagari was typed in
     * a legacy font - the extracted "text" is gibberish, so send the PDF.
     */
    private function isUsableText(string $text, Notice $notice): bool
    {
        $letters = preg_match_all('/\p{L}/u', $text);
        if ($letters < 80) {
            return false;
        }

        $devanagari = preg_match_all('/\p{Devanagari}/u', $text);
        $expectsNepali = (bool) preg_match('/\p{Devanagari}/u', $notice->title_original);

        return ! $expectsNepali || $devanagari / max($letters, 1) > 0.3;
    }

    private function sniffType(string $bytes, string $declared, string $url): string
    {
        if (str_starts_with($bytes, '%PDF')) {
            return 'application/pdf';
        }
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($bytes, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return $declared ?: (preg_match('#\.pdf(\?|$)#i', $url) ? 'application/pdf' : '');
    }

    private function isFileUrl(string $url): bool
    {
        return (bool) preg_match('#\.(pdf|jpe?g|png|webp)(\?|$)#i', $url);
    }

    private function absolute(string $href, string $base): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $p = parse_url($base);
        $origin = $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
        if (str_starts_with($href, '//')) {
            return $p['scheme'].':'.$href;
        }

        return str_starts_with($href, '/') ? $origin.$href : $origin.rtrim(dirname($p['path'] ?? '/'), '/').'/'.$href;
    }
}
