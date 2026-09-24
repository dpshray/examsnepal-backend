<?php

namespace Tests\Unit\Notices;

use App\Services\Notices\Support\NoticeTypeClassifier;
use Tests\TestCase;

class NoticeTypeClassifierTest extends TestCase
{
    public function test_classifies_real_titles(): void
    {
        $cases = [
            'स्पेसियालिटी तहको शैक्षिक कार्यक्रमको नतिजा प्रकाशनसम्बन्धी सूचना' => 'result',
            'M.Sc. Engineering Geology I Semester-2082 Exam Result' => 'result',
            'प्रदेश निजामती सेवा तर्फको चौथो तहका पदहरुको लिखित परीक्षा कार्यक्रम तोकिएको सूचना' => 'exam_schedule',
            'जनपद प्रहरी समूहतर्फ प्रहरी सहायक निरीक्षक पदहरूको विज्ञापन प्रकाशन सम्बन्धी सूचना' => 'vacancy',
            'अन्तर्वार्ता कार्यक्रम सम्बन्धी सूचना ।' => 'interview',
            'पाठ्यक्रम परिमार्जन भएको सम्बन्धमा ।' => 'syllabus',
            'नेपाल फार्मेसी परिषद्को ३१ औ नाम दर्ता परीक्षा' => 'license_exam',
            'प्रवेशपत्र डाउनलोड सम्बन्धी सूचना' => 'admit_card',
            'Notice for the Extension of Last Date of the Application' => 'entrance_form',
            'Heartfelt Condolences and Appeal!' => 'other',
        ];

        foreach ($cases as $title => $expected) {
            $this->assertSame($expected, NoticeTypeClassifier::classify($title), $title);
        }
    }
}
