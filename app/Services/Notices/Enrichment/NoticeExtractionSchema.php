<?php

namespace App\Services\Notices\Enrichment;

use App\Models\Notice;
use App\Services\Notices\Support\NepaliDate;

/**
 * JSON schema sent as output_config.format, plus the server-side validation
 * the pipeline applies before trusting a response (structured outputs
 * guarantee shape, not sense).
 */
class NoticeExtractionSchema
{
    public const DATE_FIELDS = ['published_date', 'application_start', 'application_deadline', 'double_fee_deadline', 'exam_date'];

    public static function schema(): array
    {
        $nullableString = ['anyOf' => [['type' => 'string'], ['type' => 'null']]];
        $properties = [
            'is_relevant' => ['type' => 'boolean', 'description' => 'false for tenders, procurement, auctions, internal circulars, greetings, news unrelated to exams/recruitment/admission/licensing'],
            'notice_type' => ['type' => 'string', 'enum' => Notice::TYPES],
            'title_en' => ['type' => 'string'],
            'title_ne' => ['type' => 'string'],
            'summary_en' => ['type' => 'string'],
            'summary_ne' => ['type' => 'string'],
            'organization' => ['type' => 'string'],
            'province' => ['anyOf' => [['type' => 'string', 'enum' => config('notices.provinces')], ['type' => 'null']]],
        ];

        foreach (self::DATE_FIELDS as $field) {
            $properties["{$field}_bs"] = $nullableString + ['description' => 'Bikram Sambat date exactly as stated, formatted YYYY-MM-DD, or null'];
            $properties["{$field}_ad"] = $nullableString + ['description' => 'Gregorian date only if the notice itself states it in AD, formatted YYYY-MM-DD, or null'];
        }

        $properties += [
            'posts' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'service_group' => $nullableString,
                    'level' => $nullableString,
                    'seats' => ['anyOf' => [['type' => 'integer'], ['type' => 'null']]],
                    'qualification' => $nullableString,
                ],
                'required' => ['name', 'service_group', 'level', 'seats', 'qualification'],
                'additionalProperties' => false,
            ]],
            'fees' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => ['label' => ['type' => 'string'], 'amount' => ['type' => 'string']],
                'required' => ['label', 'amount'],
                'additionalProperties' => false,
            ]],
            'eligibility' => ['type' => 'array', 'items' => ['type' => 'string']],
            'exam_centers' => ['type' => 'array', 'items' => ['type' => 'string']],
            'exam_tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'confidence' => ['type' => 'number', 'description' => '0.0-1.0: how completely and unambiguously the fields could be read from the document'],
        ];

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array{0: bool, 1: string[]} [valid, errors]
     */
    public static function validate(mixed $data): array
    {
        $errors = [];
        if (! is_array($data)) {
            return [false, ['response is not a JSON object']];
        }

        foreach (array_keys(self::schema()['properties']) as $key) {
            if (! array_key_exists($key, $data)) {
                $errors[] = "missing {$key}";
            }
        }
        if ($errors) {
            return [false, $errors];
        }

        if (! is_bool($data['is_relevant'])) {
            $errors[] = 'is_relevant must be boolean';
        }
        if (! in_array($data['notice_type'], Notice::TYPES, true)) {
            $errors[] = 'invalid notice_type';
        }
        if (! is_numeric($data['confidence']) || $data['confidence'] < 0 || $data['confidence'] > 1) {
            $errors[] = 'confidence must be between 0 and 1';
        }
        if ($data['province'] !== null && ! in_array($data['province'], config('notices.provinces'), true)) {
            $errors[] = 'invalid province';
        }
        foreach (['title_en', 'summary_en'] as $key) {
            if ($data['is_relevant'] === true && (! is_string($data[$key]) || trim($data[$key]) === '')) {
                $errors[] = "{$key} is empty";
            }
        }
        if (is_string($data['title_en']) && mb_strlen($data['title_en']) > 300) {
            $errors[] = 'title_en too long';
        }
        foreach (self::DATE_FIELDS as $field) {
            $bs = $data["{$field}_bs"];
            if ($bs !== null && NepaliDate::normalizeBs((string) $bs) === null) {
                $errors[] = "{$field}_bs is not a valid BS date";
            }
            $ad = $data["{$field}_ad"];
            if ($ad !== null && ! preg_match('/^(19|20)\d\d-\d\d-\d\d$/', (string) $ad)) {
                $errors[] = "{$field}_ad is not YYYY-MM-DD";
            }
        }
        foreach (['posts', 'fees', 'eligibility', 'exam_centers', 'exam_tags'] as $key) {
            if (! is_array($data[$key])) {
                $errors[] = "{$key} must be an array";
            }
        }
        foreach ((array) $data['posts'] as $i => $post) {
            if (! is_array($post) || ! is_string($post['name'] ?? null)) {
                $errors[] = "posts[{$i}] invalid";
            } elseif (($post['seats'] ?? null) !== null && (! is_int($post['seats']) || $post['seats'] < 0 || $post['seats'] > 100000)) {
                $errors[] = "posts[{$i}].seats invalid";
            }
        }

        return [$errors === [], $errors];
    }
}
