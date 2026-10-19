<?php

namespace Tests\Unit;

use App\Services\Seo\McqIndexability;
use App\Support\MojibakeFixer;
use PHPUnit\Framework\TestCase;

class McqIndexabilityTest extends TestCase
{
    private const GOOD_EXPLANATION = 'Tinea versicolor is confirmed by KOH microscopy, which shows the classic spaghetti-and-meatballs hyphae.';

    public function test_a_full_question_with_a_real_explanation_is_indexable(): void
    {
        $this->assertNull((new McqIndexability)->reason('<p>The best test to establish the diagnosis is:</p>', self::GOOD_EXPLANATION, false));
    }

    public function test_answer_only_explanations_are_thin(): void
    {
        $rules = new McqIndexability;

        foreach (['Ans: (c)', 'c.', 'Ref: KDT 6/e p429', '', null] as $explanation) {
            $this->assertSame(McqIndexability::THIN_EXPLANATION, $rules->reason('Antipsychotic drug of choice in resistant schizophrenia is', $explanation, false));
        }
    }

    public function test_placeholder_stems_are_missing_questions_unless_an_image_carries_the_question(): void
    {
        $rules = new McqIndexability;

        foreach (['The question is given below', 'Answer the below question.', 'Tick the correct Answer:', 'identify the image', 'Question', 'Hg'] as $stem) {
            $this->assertSame(McqIndexability::MISSING_QUESTION, $rules->reason($stem, self::GOOD_EXPLANATION, false), $stem);
        }

        $this->assertNull($rules->reason('Identify the image', self::GOOD_EXPLANATION, true));
        $this->assertNull($rules->reason('Find the odd one out: liver, kidney, femur, spleen', self::GOOD_EXPLANATION, false));
    }

    public function test_text_still_garbled_after_repair_is_flagged(): void
    {
        $this->assertSame(McqIndexability::GARBLED, (new McqIndexability)->reason('X-rays and g Ã¢â‚¬â€œrays of same energies differ by', self::GOOD_EXPLANATION, false));
    }

    public function test_duplicate_key_ignores_case_markup_and_option_order(): void
    {
        $rules = new McqIndexability;

        $this->assertSame(
            $rules->duplicateKey('<p>Drug of choice for MRSA?</p>', ['Vancomycin', 'Penicillin']),
            $rules->duplicateKey('drug of choice for  MRSA?', ['penicillin', '<b>Vancomycin</b>']),
        );
        $this->assertNotSame(
            $rules->duplicateKey('Drug of choice for MRSA?', ['Vancomycin', 'Penicillin']),
            $rules->duplicateKey('Drug of choice for MRSA?', ['Linezolid', 'Penicillin']),
        );
    }

    public function test_mojibake_fixer_recovers_triple_encoded_devanagari(): void
    {
        $original = 'हिम शिखर र हिम श्रृङ्खलाबीच जोडा मिलाउनुहोस्';
        $garbled = $original;
        for ($i = 0; $i < 3; $i++) {
            $garbled = mb_convert_encoding($garbled, 'UTF-8', 'Windows-1252');
        }

        $this->assertSame($original, MojibakeFixer::fix($garbled));
        $this->assertSame('Horner’s β, clean text', MojibakeFixer::fix('Horner’s β, clean text'));
    }
}
