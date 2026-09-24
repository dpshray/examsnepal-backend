<?php

namespace Tests\Unit\Notices;

use App\Services\Notices\Enrichment\NoticeExtractionSchema;
use Tests\TestCase;

class NoticeExtractionSchemaTest extends TestCase
{
    public static function validPayload(array $overrides = []): array
    {
        $base = [
            'is_relevant' => true,
            'notice_type' => 'vacancy',
            'title_en' => 'PSC First Class Officer Vacancy 2083',
            'title_ne' => 'निजामती सेवाका राजपत्राङ्कित प्रथम श्रेणी विज्ञापन',
            'summary_en' => 'The Public Service Commission has advertised first-class posts.',
            'summary_ne' => 'लोक सेवा आयोगले प्रथम श्रेणीका पदको विज्ञापन गरेको छ।',
            'organization' => 'Public Service Commission',
            'province' => null,
            'posts' => [['name' => 'Joint Secretary', 'service_group' => 'Health', 'level' => 'First Class', 'seats' => 3, 'qualification' => null]],
            'fees' => [['label' => 'Application fee', 'amount' => 'Rs. 2000']],
            'eligibility' => ['Master\'s degree'],
            'exam_centers' => [],
            'exam_tags' => ['section-officer'],
            'confidence' => 0.92,
        ];
        foreach (NoticeExtractionSchema::DATE_FIELDS as $field) {
            $base["{$field}_bs"] = null;
            $base["{$field}_ad"] = null;
        }
        $base['application_deadline_bs'] = '2083-06-21';

        return array_replace($base, $overrides);
    }

    public function test_schema_requires_every_property_and_forbids_extras(): void
    {
        $schema = NoticeExtractionSchema::schema();
        $this->assertFalse($schema['additionalProperties']);
        $this->assertEqualsCanonicalizing(array_keys($schema['properties']), $schema['required']);
    }

    public function test_valid_payload_passes(): void
    {
        [$valid, $errors] = NoticeExtractionSchema::validate(self::validPayload());
        $this->assertTrue($valid, implode(', ', $errors));
    }

    public function test_rejects_bad_values(): void
    {
        $cases = [
            'confidence > 1' => ['confidence' => 1.4],
            'unknown type' => ['notice_type' => 'tender'],
            'bad bs date' => ['exam_date_bs' => '2083-15-40'],
            'bad ad date' => ['exam_date_ad' => '24/09/2026'],
            'unknown province' => ['province' => 'province-1'],
            'negative seats' => ['posts' => [['name' => 'X', 'service_group' => null, 'level' => null, 'seats' => -1, 'qualification' => null]]],
            'empty english title' => ['title_en' => ''],
        ];

        foreach ($cases as $label => $override) {
            [$valid] = NoticeExtractionSchema::validate(self::validPayload($override));
            $this->assertFalse($valid, $label);
        }

        $missing = self::validPayload();
        unset($missing['posts']);
        $this->assertFalse(NoticeExtractionSchema::validate($missing)[0]);
        $this->assertFalse(NoticeExtractionSchema::validate('not json')[0]);
    }

    public function test_irrelevant_notice_may_have_empty_titles(): void
    {
        [$valid] = NoticeExtractionSchema::validate(self::validPayload(['is_relevant' => false, 'title_en' => '', 'summary_en' => '']));
        $this->assertTrue($valid);
    }
}
