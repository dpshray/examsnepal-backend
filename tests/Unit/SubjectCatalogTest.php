<?php

namespace Tests\Unit;

use App\Services\Subjects\SubjectCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SubjectCatalogTest extends TestCase
{
    private const MEDICAL = 1;
    private const ENTRANCE = 3;
    private const NURSING = 5;
    private const DENTAL = 9;
    private const PHARMACY = 11;

    /** Real exam names from the database. */
    public static function tagged(): array
    {
        return [
            ['MDMS Pathology-7-Anemia and Red Blood Cells-116', self::MEDICAL, 'Pathology'],
            ['Sprint Quiz Forensic Medicine', self::MEDICAL, 'Forensic Medicine'],
            ['Sprint Quiz Community Medicine ', self::MEDICAL, 'Community Medicine'],
            ['MDMS Comm Medicine-16-Biostatistics-116', self::MEDICAL, 'Community Medicine'],
            ['Sprint Quiz Medicine', self::MEDICAL, 'Medicine'],
            ['MDMS Ortho-13-Sports Injury-111', self::MEDICAL, 'Orthopedics'],
            ['MDMS Gyane-12-Endometriosis and Dysmenorrhea-118', self::MEDICAL, 'Obstetrics & Gynaecology'],
            ['MDMS OBG Gynae Based Sprint Quiz', self::MEDICAL, 'Obstetrics & Gynaecology'],
            ['NMCLE Subject Based Mock Test - Anesthesia', self::MEDICAL, 'Anaesthesia'],
            // Series subject wins over a subject mentioned in the topic.
            ['MDMS ENT-1-Anatomy of Ear-115', self::MEDICAL, 'ENT'],
            ['MDMS Pharma-9-Anaesthesia-115', self::MEDICAL, 'Pharmacology'],
            ['MDMS Ortho-18-Pediatric Orthopedics -111', self::MEDICAL, 'Orthopedics'],
            ['MDMS Community Medicine History of Medicine', self::MEDICAL, 'Community Medicine'],
            ['MDMS Forensic Forensic Psychiatry', self::MEDICAL, 'Forensic Medicine'],
            // Same word, different domain.
            ['MDS Orthodontics and Dentofacial Orthopedics based on Sprint Quiz', self::DENTAL, 'Orthodontics'],
            ['Sprint Quiz Conservative Dentistry &amp; Endodontics CBQs', self::DENTAL, 'Conservative Dentistry & Endodontics'],
            ['Sprint Quiz Pediatric &amp;Preventive Dentistry MCQs', self::DENTAL, 'Pediatric & Preventive Dentistry'],
            ['MDS-Pathalogy', self::DENTAL, 'Pathology'],
            ['MDS-Oral Pathology and Microbiology', self::DENTAL, 'Oral Pathology'],
            ['Nursing PCL/BNS/BSc Nursing Mid wifery based on Sprint Quiz', self::NURSING, 'Midwifery'],
            // Entrance: explicit subject beats topic; topics map to subjects.
            ['MBBS Chemistry Thermodynamics', self::ENTRANCE, 'Chemistry'],
            ['Heat and Thermodynamics', self::ENTRANCE, 'Physics'],
            ['Gravitation', self::ENTRANCE, 'Physics'],
            ['Fungi', self::ENTRANCE, 'Botany'],
            ['Frog- Reproductive System', self::ENTRANCE, 'Zoology'],
            ['27 march Bio exams', self::ENTRANCE, 'Biology'],
        ];
    }

    #[DataProvider('tagged')]
    public function test_infers_the_subject(string $name, int $examType, string $subject): void
    {
        $this->assertSame(['subject' => $subject, 'reason' => 'matched'], SubjectCatalog::infer($name, $examType));
    }

    public static function untagged(): array
    {
        return [
            ['Sprint Quiz All Subjects Set - B', self::MEDICAL, 'multi_subject'],
            ['MDMS Revision Exam Set 5', self::MEDICAL, 'multi_subject'],
            ['NMCLE Subject Based Mock Test - Basic Science', self::MEDICAL, 'multi_subject'],
            ['MDMS Subject Test (Pathology+Orthopedics) 5th July', self::MEDICAL, 'multi_subject'],
            ['Pharmacology, Medicine and Pathology Test', self::MEDICAL, 'ambiguous'],
            ['Quiz for Microbiology and Opthalmology', self::MEDICAL, 'ambiguous'],
            ['MDMS Medicine and Surgery Based on Sprint Quiz', self::MEDICAL, 'ambiguous'],
            ['MDMS CEE Standard Mock Test 27th March 2020 7 PM', self::MEDICAL, 'no_match'],
            ['Pharmacy 5th Level Lok Sewa Free Quiz', self::PHARMACY, 'no_domain'],
        ];
    }

    #[DataProvider('untagged')]
    public function test_leaves_unclear_exams_untagged(string $name, int $examType, string $reason): void
    {
        $this->assertSame(['subject' => null, 'reason' => $reason], SubjectCatalog::infer($name, $examType));
    }
}
