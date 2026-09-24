<?php

namespace Tests\Unit\Notices;

use App\Services\Notices\Support\TitleNormalizer;
use Tests\TestCase;

class TitleNormalizerTest extends TestCase
{
    public function test_clean_collapses_whitespace_and_entities(): void
    {
        $this->assertSame('Notice & Result', TitleNormalizer::clean("  Notice&nbsp;&amp;\n\t Result \u{200B} "));
    }

    public function test_key_unifies_digits_and_drops_punctuation(): void
    {
        $this->assertSame(
            TitleNormalizer::key('वि.नं. १०९-११७/२०८२-८३ सूचना।'),
            TitleNormalizer::key('वि नं 109 117 2082 83 सूचना')
        );
    }

    public function test_canonical_url_ignores_www_trailing_slash_tracking_params_and_param_order(): void
    {
        $this->assertSame(
            TitleNormalizer::canonicalUrl('https://www.nrb.org.np/category/notices/?department=hrm&utm_source=x'),
            TitleNormalizer::canonicalUrl('http://nrb.org.np/category/notices?department=hrm')
        );
    }

    public function test_content_hash_is_stable_across_cosmetic_differences(): void
    {
        $a = TitleNormalizer::contentHash(1, 'सूचना नं. ५९/०८३-०८४', 'https://ppsc.lumbini.gov.np/notice/218');
        $b = TitleNormalizer::contentHash(1, '  सूचना नं. 59/083-084 ', 'https://ppsc.lumbini.gov.np/notice/218/');
        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
        $this->assertNotSame($a, TitleNormalizer::contentHash(2, 'सूचना नं. ५९/०८३-०८४', 'https://ppsc.lumbini.gov.np/notice/218'));
    }

    public function test_similarity_on_devanagari(): void
    {
        $original = 'प्रदेश निजामती सेवा तर्फको विभिन्न सेवा, समूह, उपसमूह, चौथो तहका पदहरुको लिखित परीक्षा कार्यक्रम';
        $this->assertSame(100.0, TitleNormalizer::similarity($original, $original.' ।'));
        $this->assertGreaterThan(90, TitleNormalizer::similarity($original, str_replace('पदहरुको', 'पदहरूको', $original)));
        $this->assertLessThan(60, TitleNormalizer::similarity($original, 'स्नातक तहको आवेदन रुजुसम्बन्धी सूचना'));
    }
}
