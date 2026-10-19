<?php

namespace App\Services\Seo;

use App\Support\MojibakeFixer;

/**
 * Decides whether a public MCQ page is worth indexing. ~174k of the site's
 * indexable URLs are single-question pages; Google judges the whole site
 * partly on their quality, so pages a searcher would bounce from (an answer
 * letter with no explanation, a question whose text/image was lost in import,
 * unreadable encoding) are kept out of the index - they stay live for users.
 */
class McqIndexability
{
    public const THIN_EXPLANATION = 'thin_explanation';
    public const MISSING_QUESTION = 'missing_question';
    public const GARBLED = 'garbled';

    /** Explanations shorter than this are answer-only ("Ans: (c)", "c."). */
    public const MIN_EXPLANATION_CHARS = 60;

    /**
     * Stems that stand in for a question that only existed as an image in the
     * source document (the image never made it across), e.g. 334 rows of
     * "The question is given below".
     */
    private const PLACEHOLDER_STEM = '/^(the\s+)?(question|answer|find|tick|choose|select|identify|see|refer|look|study)\b'
        .'(\s+(is|are|the|a|an|given|below|above|following|correct|question|answer|image|figure|diagram|picture|to|at|in|of|and))*[\s.:?!-]*$/i';

    // Re-encoding residue only - a lone U+FFFD in otherwise readable text isn't enough.
    private const MOJIBAKE = '/Ã|Â|â€/u';

    /** Null when indexable, otherwise one of the reason constants. */
    public function reason(?string $question, ?string $explanation, bool $hasQuestionImage): ?string
    {
        $stem = self::plain(MojibakeFixer::fix($question));
        $why = self::plain(MojibakeFixer::fix($explanation));

        if (preg_match(self::MOJIBAKE, $stem.' '.$why)) {
            return self::GARBLED;
        }

        if (! $hasQuestionImage && (mb_strlen($stem) < 8 || preg_match(self::PLACEHOLDER_STEM, $stem))) {
            return self::MISSING_QUESTION;
        }

        if (mb_strlen($why) < self::MIN_EXPLANATION_CHARS) {
            return self::THIN_EXPLANATION;
        }

        return null;
    }

    /**
     * Key that's identical for exact duplicates: same question text and the
     * same set of options, ignoring case, markup and option order.
     */
    public function duplicateKey(?string $question, iterable $options): string
    {
        $normalized = [];
        foreach ($options as $option) {
            $normalized[] = mb_strtolower(self::plain(MojibakeFixer::fix($option)));
        }
        sort($normalized);

        return md5(mb_strtolower(self::plain(MojibakeFixer::fix($question))).'#'.implode('|', $normalized));
    }

    public static function plain(?string $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
