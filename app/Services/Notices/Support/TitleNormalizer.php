<?php

namespace App\Services\Notices\Support;

/**
 * Normalization used for hashing and duplicate detection only - the
 * original title is always kept for display.
 */
class TitleNormalizer
{
    /** Trim + collapse whitespace (including NBSP / zero-width chars). */
    public static function clean(string $title): string
    {
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $title);
        $title = preg_replace('/[\s\x{00A0}]+/u', ' ', $title);

        return trim($title, " \t\n\r\0\x0B|-–—");
    }

    /** Key used for hashing/fuzzy matching: digits unified, punctuation and case dropped. */
    public static function key(string $title): string
    {
        $t = NepaliDate::toLatinDigits(self::clean($title));
        $t = mb_strtolower($t);
        // Danda, purna viram and ASCII punctuation are noise for matching.
        $t = preg_replace('/[।॥\p{P}\p{S}]+/u', ' ', $t);
        $t = preg_replace('/\s+/u', ' ', $t);

        return trim($t);
    }

    public static function canonicalUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! $parts || empty($parts['host'])) {
            return trim($url);
        }

        $host = strtolower(preg_replace('/^www\./i', '', $parts['host']));
        $path = rtrim($parts['path'] ?? '/', '/') ?: '/';
        $query = '';
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $q);
            // Tracking params never identify a notice.
            $q = array_filter($q, fn ($k) => ! preg_match('/^(utm_|fbclid|gclid)/i', (string) $k), ARRAY_FILTER_USE_KEY);
            ksort($q);
            $query = $q ? '?'.http_build_query($q) : '';
        }

        return $host.$path.$query;
    }

    public static function contentHash(int $sourceId, string $title, string $url): string
    {
        return hash('sha256', $sourceId.'|'.self::key($title).'|'.self::canonicalUrl($url));
    }

    /** Similarity in percent between two titles' normalized keys. */
    public static function similarity(string $a, string $b): float
    {
        $a = self::key($a);
        $b = self::key($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 100.0;
        }

        // Levenshtein over code points (PHP's levenshtein()/similar_text()
        // work on bytes, which skews multi-byte Devanagari).
        $ca = mb_str_split($a);
        $cb = mb_str_split($b);
        $n = count($ca);
        $m = count($cb);
        $prev = range(0, $m);
        for ($i = 1; $i <= $n; $i++) {
            $cur = [$i];
            for ($j = 1; $j <= $m; $j++) {
                $cost = $ca[$i - 1] === $cb[$j - 1] ? 0 : 1;
                $cur[$j] = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + $cost);
            }
            $prev = $cur;
        }

        return (1 - $prev[$m] / max($n, $m)) * 100;
    }
}
