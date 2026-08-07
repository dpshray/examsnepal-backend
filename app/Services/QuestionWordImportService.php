<?php

namespace App\Services;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\IOFactory;

class QuestionWordImportService
{
    private const OPTION_LETTERS = ['A', 'B', 'C', 'D'];

    /**
     * Parse an uploaded .docx file into validated question DTOs.
     *
     * Expected format per question:
     *   1. Question text
     *   A) Option text *      (a trailing "*" marks the correct option)
     *   B) Option text
     *   C) Option text
     *   D) Option text
     *   Explanation: explanation text
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

        $questions = [];
        $current = null; // ['qIndex' => int, 'field' => 'question'|'option'|'explanation', 'letter' => ?string]

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                ['text' => $text, 'images' => $images] = $this->extractParagraph($element);

                if (preg_match('/^(\d+)[.\)]\s*(.+)$/u', $text, $m)) {
                    $questions[] = [
                        'number' => (int) $m[1],
                        'text' => trim($m[2]),
                        'options' => [],
                        'explanation' => '',
                        'image' => null,
                        'explanation_image' => null,
                    ];
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

                if ($current !== null && preg_match('/^explanation\s*:?\s*(.*)$/iu', $text, $m)) {
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
        }

        return $this->validate($questions);
    }

    /**
     * @return array{text: string, images: Image[]}
     */
    private function extractParagraph($element): array
    {
        $text = '';
        $images = [];

        if ($element instanceof Text) {
            $text = (string) $element->getText();
        } elseif ($element instanceof Image) {
            $images[] = $element;
        } elseif ($element instanceof AbstractContainer) {
            foreach ($element->getElements() as $child) {
                if ($child instanceof Text) {
                    $text .= $child->getText();
                } elseif ($child instanceof Image) {
                    $images[] = $child;
                } elseif ($child instanceof AbstractContainer) {
                    foreach ($child->getElements() as $grandchild) {
                        if ($grandchild instanceof Text) {
                            $text .= $grandchild->getText();
                        } elseif ($grandchild instanceof Image) {
                            $images[] = $grandchild;
                        }
                    }
                }
            }
        }

        return ['text' => trim($text), 'images' => $images];
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
                $reasons[] = 'No correct option marked (add "*" after the correct option)';
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
