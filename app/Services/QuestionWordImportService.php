<?php

namespace App\Services;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;

class QuestionWordImportService
{
    private const OPTION_LETTERS = ['A', 'B', 'C', 'D'];

    /**
     * Parse an uploaded .docx file into validated question DTOs.
     *
     * Two paragraph styles are supported and may be mixed within the same
     * document:
     *
     *   1) One field per paragraph, each with a literal leading number/letter:
     *        1. Question text
     *        A) Option text *      (a trailing "*" marks the correct option)
     *        B) Option text
     *        C) Option text
     *        D) Option text
     *        Explanation: explanation text
     *
     *   2) A single paragraph per question, using Word's native numbered-list
     *      style for the question number (no literal digit text is present
     *      in this case) with manual line breaks (Shift+Enter) separating the
     *      stem, the options and the explanation.
     *
     * A correct option may also be marked with its own "Answer: B" line
     * instead of a trailing "*". "Explanation:" may also be written as
     * "Solution:". Question numbers may be prefixed with "Q"/"Question".
     *
     * An optional image may be placed as its own line/paragraph directly
     * under the question, an option, or the explanation - the first image
     * found for a given field is used, later ones for the same field are ignored.
     *
     * @return array{valid: array<int, array>, errors: array<int, array{question_number: int, reason: string}>}
     */
    public function parse(string $filePath): array
    {
        $phpWord = IOFactory::load($filePath, 'Word2007');

        $lines = [];
        foreach ($phpWord->getSections() as $section) {
            $lines = array_merge($lines, $this->flattenSection($section));
        }

        $questions = [];
        $current = null; // ['qIndex' => int, 'field' => 'question'|'option'|'explanation'|'answer_marker', 'letter' => ?string]

        foreach ($lines as $line) {
            $text = $line['text'];
            $images = $line['images'];

            if ($line['isListItemStart']) {
                $questions[] = $this->newQuestion(count($questions) + 1, $text);
                $current = ['qIndex' => count($questions) - 1, 'field' => 'question', 'letter' => null];
                $this->attachImage($questions, $current, $images[0] ?? null);
                continue;
            }

            if (preg_match('/^(?:q(?:uestion)?\.?\s*)?(\d+)[.\):]\s*(.+)$/iu', $text, $m)) {
                $questions[] = $this->newQuestion((int) $m[1], $m[2]);
                $current = ['qIndex' => count($questions) - 1, 'field' => 'question', 'letter' => null];
                $this->attachImage($questions, $current, $images[0] ?? null);
                continue;
            }

            if ($current !== null && preg_match('/^([A-Da-d])[.\)]\s*(.*)$/u', $text, $m)) {
                $letter = strtoupper($m[1]);
                [$optionText, $isCorrect] = $this->stripCorrectMarker(trim($m[2]));
                $questions[$current['qIndex']]['options'][$letter] = [
                    'text' => $optionText,
                    'is_correct' => $isCorrect,
                    'image' => null,
                ];
                $current = ['qIndex' => $current['qIndex'], 'field' => 'option', 'letter' => $letter];
                $this->attachImage($questions, $current, $images[0] ?? null);
                continue;
            }

            if ($current !== null && preg_match('/^(?:correct\s+)?answer\s*:?\s*([A-Da-d])\b/iu', $text, $m)) {
                $letter = strtoupper($m[1]);
                if (isset($questions[$current['qIndex']]['options'][$letter])) {
                    $questions[$current['qIndex']]['options'][$letter]['is_correct'] = true;
                }
                $current = ['qIndex' => $current['qIndex'], 'field' => 'answer_marker', 'letter' => null];
                continue;
            }

            if ($current !== null && preg_match('/^(?:explanation|solution)\s*:?\s*(.*)$/iu', $text, $m)) {
                $questions[$current['qIndex']]['explanation'] = trim($m[1]);
                $current = ['qIndex' => $current['qIndex'], 'field' => 'explanation', 'letter' => null];
                $this->attachImage($questions, $current, $images[0] ?? null);
                continue;
            }

            if ($text === '') {
                if ($current !== null) {
                    $this->attachImage($questions, $current, $images[0] ?? null);
                }
                continue;
            }

            // Continuation of the previous line (wrapped text within the same field)
            if ($current !== null) {
                $this->appendText($questions, $current, $text);
                $this->attachImage($questions, $current, $images[0] ?? null);
            }
        }

        return $this->validate($questions);
    }

    private function newQuestion(int $number, string $text): array
    {
        return [
            'number' => $number,
            'text' => trim($text),
            'options' => [],
            'explanation' => '',
            'image' => null,
            'explanation_image' => null,
        ];
    }

    /**
     * Flatten a section's paragraphs into a stream of logical lines. A line
     * boundary is either a paragraph break or a manual line break
     * (Shift+Enter, i.e. a <w:br/>) within a single paragraph, so that the
     * same downstream matching logic works whether a question's fields are
     * each their own paragraph, or all packed into one paragraph separated
     * by line breaks.
     *
     * @return array<int, array{text: string, images: Image[], isListItemStart: bool}>
     */
    private function flattenSection(Section $section): array
    {
        $lines = [];

        foreach ($section->getElements() as $element) {
            if ($element instanceof ListItemRun) {
                // Word's native auto-numbered list item - the number itself
                // is rendered by Word's numbering engine and never appears
                // as literal text, so the first line of this paragraph is
                // flagged as a question boundary structurally.
                $lines = array_merge($lines, $this->flattenContainer($element, true));
            } elseif ($element instanceof TextRun) {
                $lines = array_merge($lines, $this->flattenContainer($element, false));
            } elseif ($element instanceof Text) {
                $lines[] = ['text' => trim((string) $element->getText()), 'images' => [], 'isListItemStart' => false];
            } elseif ($element instanceof Image) {
                $lines[] = ['text' => '', 'images' => [$element], 'isListItemStart' => false];
            } elseif ($element instanceof TextBreak) {
                $lines[] = ['text' => '', 'images' => [], 'isListItemStart' => false];
            }
            // Other element types (tables, text boxes, ...) aren't supported and are skipped.
        }

        return $lines;
    }

    /**
     * Walk a paragraph's runs (a TextRun, or a ListItemRun which extends it),
     * splitting into one line per TextBreak - the element PHPWord produces
     * for a manual <w:br/> line break inside a single paragraph.
     *
     * @return array<int, array{text: string, images: Image[], isListItemStart: bool}>
     */
    private function flattenContainer(AbstractContainer $container, bool $markFirstAsListItem): array
    {
        $lines = [];
        $bufferText = '';
        $bufferImages = [];
        $isFirst = true;

        $flush = function () use (&$lines, &$bufferText, &$bufferImages, &$isFirst, $markFirstAsListItem) {
            $lines[] = [
                'text' => trim($bufferText),
                'images' => $bufferImages,
                'isListItemStart' => $isFirst && $markFirstAsListItem,
            ];
            $bufferText = '';
            $bufferImages = [];
            $isFirst = false;
        };

        foreach ($container->getElements() as $child) {
            if ($child instanceof TextBreak) {
                $flush();
            } elseif ($child instanceof Text) {
                $bufferText .= $child->getText();
            } elseif ($child instanceof Image) {
                $bufferImages[] = $child;
            } elseif ($child instanceof AbstractContainer) {
                // e.g. a hyperlink run nested inside the paragraph - one extra
                // level of Text/Image collection (links can't themselves
                // contain a line break per the OOXML schema).
                foreach ($child->getElements() as $grandchild) {
                    if ($grandchild instanceof Text) {
                        $bufferText .= $grandchild->getText();
                    } elseif ($grandchild instanceof Image) {
                        $bufferImages[] = $grandchild;
                    }
                }
            }
        }

        $flush();

        return $lines;
    }

    private function stripCorrectMarker(string $text): array
    {
        if (preg_match('/\*\s*$/', $text)) {
            return [trim(preg_replace('/\*\s*$/', '', $text)), true];
        }

        return [$text, false];
    }

    private function attachImage(array &$questions, array $current, ?Image $image): void
    {
        if ($image === null) {
            return;
        }

        $q = &$questions[$current['qIndex']];

        if ($current['field'] === 'question' && $q['image'] === null) {
            $q['image'] = $image;
        } elseif ($current['field'] === 'option' && isset($q['options'][$current['letter']]) && $q['options'][$current['letter']]['image'] === null) {
            $q['options'][$current['letter']]['image'] = $image;
        } elseif ($current['field'] === 'explanation' && $q['explanation_image'] === null) {
            $q['explanation_image'] = $image;
        }
    }

    private function appendText(array &$questions, array $current, string $text): void
    {
        $q = &$questions[$current['qIndex']];

        if ($current['field'] === 'question') {
            $q['text'] = trim($q['text'] . ' ' . $text);
        } elseif ($current['field'] === 'option' && isset($q['options'][$current['letter']])) {
            [$stripped, $isCorrect] = $this->stripCorrectMarker($text);
            $q['options'][$current['letter']]['text'] = trim($q['options'][$current['letter']]['text'] . ' ' . $stripped);
            if ($isCorrect) {
                $q['options'][$current['letter']]['is_correct'] = true;
            }
        } elseif ($current['field'] === 'explanation') {
            $q['explanation'] = trim($q['explanation'] . ' ' . $text);
        }
    }

    private function validate(array $questions): array
    {
        $valid = [];
        $errors = [];

        foreach ($questions as $q) {
            $reasons = [];

            if ($q['text'] === '') {
                $reasons[] = 'Question text is empty';
            }

            foreach (self::OPTION_LETTERS as $letter) {
                if (!isset($q['options'][$letter]) || $q['options'][$letter]['text'] === '') {
                    $reasons[] = "Option {$letter} is missing";
                }
            }

            $correctCount = count(array_filter(
                $q['options'],
                fn ($option) => $option['is_correct'] ?? false
            ));

            if ($correctCount === 0) {
                $reasons[] = 'No correct option marked (add "*" after the correct option, or an "Answer: X" line)';
            } elseif ($correctCount > 1) {
                $reasons[] = 'More than one correct option marked';
            }

            if (trim($q['explanation']) === '') {
                $reasons[] = 'Explanation is missing';
            }

            if ($reasons) {
                $errors[] = [
                    'question_number' => $q['number'],
                    'reason' => implode('; ', $reasons),
                ];
            } else {
                $valid[] = $q;
            }
        }

        return ['valid' => $valid, 'errors' => $errors];
    }
}
