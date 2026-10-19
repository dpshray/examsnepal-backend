<?php

namespace App\Support;

class MojibakeFixer
{
    /**
     * Marker bytes ("Ã" encoded as UTF-8) present whenever text has been through
     * the historical UTF-8 -> Windows-1252 -> UTF-8 re-encoding bug (usually from
     * old Word-document imports). Used only to decide whether repair is worth
     * attempting at all - clean text never reaches the iconv passes below.
     */
    private const MARKER = "\xC3\x83";

    /**
     * Empirically, every corrupted row in this dataset was re-encoded exactly
     * three times over. Fewer passes leave fragments behind (e.g. "Horner’s"
     * as "â€™"); more passes overshoot and destroy valid multi-byte characters
     * (e.g. turn "β" into nothing). Three is the number that round-trips
     * correctly - verified against ~140 real corrupted rows with no
     * over-correction and no data loss on the rows it can't confidently fix.
     */
    private const PASSES = 3;

    public static function fix(?string $text): ?string
    {
        if ($text === null || $text === '' || !str_contains($text, self::MARKER)) {
            return $text;
        }

        return self::decode($text, fn ($s) => @iconv('UTF-8', 'Windows-1252//IGNORE', $s))
            // iconv can't map the five byte values Windows-1252 leaves undefined
            // (0x81, 0x8D, 0x8F, 0x90, 0x9D) - common in re-encoded Devanagari,
            // so nearly every corrupted Nepali row failed above. mbstring passes
            // those bytes through, recovering ~12k more rows. Tried second so
            // rows iconv already repairs are unaffected.
            ?? self::decode($text, fn ($s) => mb_convert_encoding($s, 'Windows-1252', 'UTF-8'))
            // Can't safely repair this one - show the mojibake rather than risk mangling it further.
            ?? $text;
    }

    private static function decode(string $text, callable $toWindows1252): ?string
    {
        $current = $text;

        for ($i = 0; $i < self::PASSES; $i++) {
            $next = $toWindows1252($current);

            if ($next === false || $next === '' || !mb_check_encoding($next, 'UTF-8')) {
                return null;
            }

            $current = $next;
        }

        return $current;
    }
}
