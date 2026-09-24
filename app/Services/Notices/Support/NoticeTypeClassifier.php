<?php

namespace App\Services\Notices\Support;

/**
 * Keyword-based notice type from the title (Nepali + English). Used when AI
 * enrichment is off so the type badge and filter still mean something; the
 * AI result replaces it when enrichment is on.
 */
class NoticeTypeClassifier
{
    // First match wins, so more specific types come first.
    private const RULES = [
        'admit_card' => ['प्रवेशपत्र', 'प्रवेश पत्र', 'admit card', 'admit-card'],
        'result' => ['नतिजा', 'परीक्षाफल', 'सिफारिस', 'उत्तीर्ण', 'result', 'merit list', 'selected candidates'],
        'interview' => ['अन्तर्वार्ता', 'अन्तरवार्ता', 'interview'],
        'syllabus' => ['पाठ्यक्रम', 'syllabus', 'curriculum'],
        'license_exam' => ['लाइसेन्स', 'लाईसेन्स', 'लाइसेन्सिङ', 'नाम दर्ता परीक्षा', 'licens', 'registration exam'],
        'exam_schedule' => ['परीक्षा कार्यक्रम', 'परीक्षा केन्द्र', 'परिक्षा कार्यक्रम', 'परीक्षा संचालन', 'परिक्षा संचालन', 'परीक्षा स्थगित', 'शारीरिक परीक्षण', 'exam schedule', 'examination schedule', 'exam centre', 'exam center', 'routine'],
        'vacancy' => ['विज्ञापन', 'बिज्ञापन', 'पदपूर्ति', 'दरखास्त आह्वान', 'दरखास्त आव्हान', 'रिक्त', 'vacancy', 'vacancies', 'recruitment', 'career'],
        'entrance_form' => ['प्रवेश परीक्षा', 'भर्ना', 'आवेदन', 'entrance', 'admission', 'application'],
    ];

    public static function classify(string ...$titles): string
    {
        $text = mb_strtolower(implode(' ', $titles));

        foreach (self::RULES as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $type;
                }
            }
        }

        return 'other';
    }
}
