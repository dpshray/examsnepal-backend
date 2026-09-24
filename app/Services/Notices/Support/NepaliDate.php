<?php

namespace App\Services\Notices\Support;

use Anuzpandey\LaravelNepaliDate\LaravelNepaliDate;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Parsing of the Bikram Sambat dates found on Nepali notice boards, plus
 * BS<->AD conversion (delegated to anuzpandey/laravel-nepali-date).
 *
 * Handles Devanagari and Latin digits, numeric formats (2083-06-08,
 * २०८३/०६/०८, 2083.6.8) and month-name formats in either order
 * (२०८३ असोज ८, ५ आश्विन २०८३, सोमबार, असोज २, २०८३, 8 Asoj 2083).
 */
class NepaliDate
{
    private const DIGITS = ['०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4', '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9'];

    // Every spelling seen on official sites, lower-cased for Latin ones.
    private const MONTHS = [
        1 => ['बैशाख', 'वैशाख', 'बैसाख', 'baisakh', 'baishakh', 'baisakha', 'vaisakh', 'vaishakh'],
        2 => ['जेठ', 'जेष्ठ', 'ज्येष्ठ', 'jestha', 'jeth', 'jyestha', 'jeshtha'],
        3 => ['असार', 'आषाढ', 'आषाढ़', 'अषाढ', 'असाढ', 'asar', 'ashadh', 'asadh', 'ashad', 'aashadh', 'asaar'],
        4 => ['साउन', 'श्रावण', 'सावन', 'shrawan', 'shravan', 'saun', 'srawan', 'sawan'],
        5 => ['भदौ', 'भाद्र', 'भाद्रपद', 'bhadra', 'bhadau', 'bhado', 'bhadau'],
        6 => ['असोज', 'आश्विन', 'आसोज', 'अश्विन', 'asoj', 'ashwin', 'aswin', 'ashoj', 'ashwoj'],
        7 => ['कात्तिक', 'कार्तिक', 'कातिक', 'kartik', 'kattik', 'kartika'],
        8 => ['मंसिर', 'मङ्सिर', 'मार्ग', 'मार्गशीर्ष', 'मंसीर', 'mangsir', 'mansir', 'marga', 'mangshir'],
        9 => ['पुस', 'पौष', 'पुष', 'poush', 'push', 'paush', 'pous'],
        10 => ['माघ', 'magh', 'magha'],
        11 => ['फागुन', 'फाल्गुन', 'फाल्गुण', 'falgun', 'phalgun', 'fagun', 'phagun'],
        12 => ['चैत', 'चैत्र', 'chaitra', 'chait', 'chaitr'],
    ];

    public static function toLatinDigits(string $text): string
    {
        return strtr($text, self::DIGITS);
    }

    /**
     * Find the first BS date in free text. Returns [year, month, day] or null.
     */
    public static function parseBs(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $t = mb_strtolower(self::toLatinDigits($text));

        // 2083-06-08, 2083/6/8, 2083.06.08, 2083।06।08 (Devanagari danda as separator)
        if (preg_match('/\b(20[6-9]\d)\s*[\-\/\.।]\s*(\d{1,2})\s*[\-\/\.।]\s*(\d{1,2})\b/u', $t, $m)) {
            return self::valid((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // 08-06-2083 (day first - occasionally used in English notices)
        if (preg_match('/\b(\d{1,2})\s*[\-\/\.]\s*(\d{1,2})\s*[\-\/\.]\s*(20[6-9]\d)\b/u', $t, $m)) {
            return self::valid((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        $monthAlternation = self::monthAlternation();

        // 2083 असोज 8 / 2083 Asoj 8
        if (preg_match('/\b(20[6-9]\d)\s*(?:साल)?\s*('.$monthAlternation.')\s*(\d{1,2})/u', $t, $m)) {
            return self::valid((int) $m[1], self::monthNumber($m[2]), (int) $m[3]);
        }

        // 8 असोज 2083 / ५ आश्विन २०८३, सोमबार / 8th Asoj, 2083
        if (preg_match('/(\d{1,2})\s*(?:st|nd|rd|th|गते)?\s*('.$monthAlternation.')\s*,?\s*(20[6-9]\d)/u', $t, $m)) {
            return self::valid((int) $m[3], self::monthNumber($m[2]), (int) $m[1]);
        }

        // असोज २, २०८३ / Asoj 2, 2083
        if (preg_match('/('.$monthAlternation.')\s*(\d{1,2})\s*(?:गते)?\s*,?\s*(20[6-9]\d)/u', $t, $m)) {
            return self::valid((int) $m[3], self::monthNumber($m[1]), (int) $m[2]);
        }

        return null;
    }

    /** Parse a BS date in free text and return it as Y-m-d BS, or null. */
    public static function normalizeBs(?string $text): ?string
    {
        $parts = self::parseBs($text);

        return $parts ? sprintf('%04d-%02d-%02d', ...$parts) : null;
    }

    /** Convert a BS date (any supported format) to an AD date, or null. */
    public static function bsToAd(?string $text): ?CarbonImmutable
    {
        $bs = self::normalizeBs($text);
        if (! $bs) {
            return null;
        }

        try {
            return CarbonImmutable::parse(LaravelNepaliDate::from($bs)->toEnglishDate());
        } catch (Throwable) {
            return null;
        }
    }

    public static function adToBs(\DateTimeInterface|string $date): ?string
    {
        try {
            $ad = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;

            return LaravelNepaliDate::from($ad)->toNepaliDate();
        } catch (Throwable) {
            return null;
        }
    }

    private static function valid(int $y, ?int $m, int $d): ?array
    {
        if (! $m || $m < 1 || $m > 12 || $d < 1 || $d > 32 || $y < 2000 || $y > 2099) {
            return null;
        }

        try {
            if ($d > LaravelNepaliDate::daysInMonth($m, $y)) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return [$y, $m, $d];
    }

    private static function monthNumber(string $name): ?int
    {
        foreach (self::MONTHS as $number => $names) {
            if (in_array($name, $names, true)) {
                return $number;
            }
        }

        return null;
    }

    private static function monthAlternation(): string
    {
        static $pattern = null;

        if ($pattern === null) {
            $all = array_merge(...array_values(self::MONTHS));
            // Longest first so "जेष्ठ" wins over "जेठ"-style prefixes.
            usort($all, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
            $pattern = implode('|', array_map(fn ($n) => preg_quote($n, '/'), $all));
        }

        return $pattern;
    }
}
