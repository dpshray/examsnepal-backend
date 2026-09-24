<?php

namespace Tests\Unit\Notices;

use App\Services\Notices\Support\NepaliDate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NepaliDateTest extends TestCase
{
    /** Known BS/AD pairs (1 Baisakh of each year + dates seen on source sites). */
    public static function knownPairs(): array
    {
        return [
            ['2070-01-01', '2013-04-14'],
            ['2075-01-01', '2018-04-14'],
            ['2080-01-01', '2023-04-14'],
            ['2081-01-01', '2024-04-13'],
            ['2082-01-01', '2025-04-14'],
            ['2083-01-01', '2026-04-14'],
            // psc.gov.np publishes both: upload_date_bs / upload_date
            ['2083-05-31', '2026-09-16'],
            ['2083-01-03', '2026-04-16'],
            ['2082-12-25', '2026-04-08'],
            ['2082-09-30', '2026-01-14'],
            ['2083-06-08', '2026-09-24'],
        ];
    }

    #[DataProvider('knownPairs')]
    public function test_bs_to_ad_matches_known_pairs(string $bs, string $ad): void
    {
        $this->assertSame($ad, NepaliDate::bsToAd($bs)?->toDateString());
        $this->assertSame($bs, NepaliDate::adToBs($ad));
    }

    public function test_covers_2070_to_2090(): void
    {
        foreach (range(2070, 2090) as $year) {
            $this->assertNotNull(NepaliDate::bsToAd("{$year}-01-01"), "1 Baisakh {$year}");
            $this->assertNotNull(NepaliDate::bsToAd("{$year}-12-01"), "1 Chaitra {$year}");
        }
    }

    public static function formats(): array
    {
        return [
            'latin iso' => ['2083-06-08', '2083-06-08'],
            'devanagari slashes' => ['२०८३/०६/०८', '2083-06-08'],
            'devanagari dashes' => ['२०८३-०६-०२', '2083-06-02'],
            'single digits + dots' => ['2083.6.8', '2083-06-08'],
            'danda separators' => ['मिति २०८२।१२।२५', '2082-12-25'],
            'year month-name day' => ['२०८३ असोज ८', '2083-06-08'],
            'year month-name day (sudurpashchim)' => ['२०८२ चैत २७', '2082-12-27'],
            'day month-name year weekday (MEC)' => ['५ आश्विन २०८३, सोमबार', '2083-06-05'],
            'day month-name year (jestha spelling)' => ['३२ जेष्ठ २०८०, बिहिबार', '2080-02-32'],
            'aashadh with nukta' => ['१९ आषाढ़ २०८०', '2080-03-19'],
            'month day, year (lumbini)' => ['असोज २, २०८३', '2083-06-02'],
            'latin month name' => ['8 Asoj 2083', '2083-06-08'],
            'embedded in title' => ['(मिति २०८३/०५/२९) करार सूचना', '2083-05-29'],
            'prefixed label' => ['प्रकाशित मिति: २०८३-०६-०२', '2083-06-02'],
        ];
    }

    #[DataProvider('formats')]
    public function test_parses_notice_board_formats(string $input, string $expected): void
    {
        $this->assertSame($expected, NepaliDate::normalizeBs($input));
    }

    public function test_rejects_impossible_and_ad_dates(): void
    {
        $this->assertNull(NepaliDate::normalizeBs('2083-13-01'));
        $this->assertNull(NepaliDate::normalizeBs('2083-01-35'));
        $this->assertNull(NepaliDate::normalizeBs('2026-09-24'), 'AD dates are not BS');
        $this->assertNull(NepaliDate::normalizeBs('Mon, 14 Sep 2026 10:31:12 +0000'));
        $this->assertNull(NepaliDate::normalizeBs(''));
        $this->assertNull(NepaliDate::normalizeBs(null));
    }

    public function test_devanagari_digits(): void
    {
        $this->assertSame('2083/06/08', NepaliDate::toLatinDigits('२०८३/०६/०८'));
    }
}
