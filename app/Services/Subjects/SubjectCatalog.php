<?php

namespace App\Services\Subjects;

/**
 * Infers an exam's subject from its name, e.g.
 *   "MDMS Pathology-7-Anemia and Red Blood Cells-116" -> Pathology
 *   "Sprint Quiz Conservative Dentistry & Endodontics" -> Conservative Dentistry & Endodontics
 *   "Gravitation" (MBBS entrance)                     -> Physics
 *
 * Rules, in order:
 *  1. Names that are clearly multi-subject ("All Subjects", "Revision",
 *     "Basic Science", "Full Mock"...) get no subject.
 *  2. Subjects are scoped to the exam type's domain, so "Ortho" means
 *     Orthopedics for medical exams and Orthodontics for dental ones.
 *  3. A subject named explicitly beats topic keywords ("MBBS Chemistry
 *     Thermodynamics" is Chemistry even though thermodynamics is also physics).
 *  4. Series names ("MDMS ENT-1-Anatomy of Ear", "MDMS Pharma-9-Anaesthesia")
 *     belong to the subject right after the series prefix: the rest is a
 *     topic within it. Lists ("Pharmacology, Medicine and Pathology") don't.
 *  5. If two or more subjects still match, the exam is left untagged -
 *     a wrong subject is worse than none.
 */
class SubjectCatalog
{
    /** Series prefixes that come before the subject in a name. */
    private const PREFIX = '/^(?:(?:MDMS|MD\/MS|MDS|NMCLE|BDS|MBBS(?:\s+CEE)?|Sprint\s+Quiz|Lok\s*Sewa|Nursing\s+PCL\/BNS\/BSc(?:\s+Nursing)?)[\s\/:-]*)*/i';

    private const MULTI = '/\ball[\s-]*subjects?\b|\brevision\b|\bbasic\s+science|\bclinical\s+science|\bfull\s+mock|\bmarathon\b|\bmixed\b|\bsubject\s+test\b|\bgeneral\s+(knowledge|awareness)\b|\+/i';

    /**
     * domain => [exam_type_ids, subjects => [name => [explicit regex, topic regex|null]]]
     * Regexes are case-insensitive and run on a normalised name.
     */
    public static function domains(): array
    {
        return [
            'medical' => [
                'exam_type_ids' => [1, 4],
                'subjects' => [
                    'Anatomy' => ['\banatomy\b', '\b(embryology|neuroanatomy|histology|upper\s+limb|lower\s+limb|thorax|abdomen|head\s+and\s+neck)\b'],
                    'Physiology' => ['\bphysiology\b', '\b(body\s+fluid|nerve\s+muscle|cell\s+membrane|cardiovascular\s+physiology)\b'],
                    'Biochemistry' => ['\bbio-?c?hemistry\b', '\b(enzymes?|vitamins?|metabolism\s+of)\b'],
                    'Pathology' => ['\bpatholog(y|i)\b', '\b(neoplasia|anemia|inflammation|hemodynamic)\b'],
                    'Pharmacology' => ['\bpharma(cology)?\b', null],
                    'Microbiology' => ['\bmicrobiology\b', '\b(bacteriology|virology|parasitology|mycology|immunology)\b'],
                    'Forensic Medicine' => ['\bforensic\b|\bfmt\b', '\b(toxicology|abortion|autopsy|post\s*mortem)\b'],
                    'Community Medicine' => ['\bcomm(unity|\.)?\s+medicine\b|\bpsm\b|\bpreventive\s+and\s+social\b', '\b(epidemiology|biostatistics|nutrition)\b'],
                    'Medicine' => ['(?<!forensic\s)(?<!community\s)(?<!comm\s)(?<!comm\.\s)(?<!oral\s)\b(internal\s+)?medicine\b', null],
                    'Surgery' => ['(?<!oral\s)(?<!maxillofacial\s)(?<!alveolar\s)\bsurgery\b', null],
                    'Obstetrics & Gynaecology' => ['\bobg\b|\bgy?nae?c?|\bgyane\b|\bobstetric', '\b(endometriosis|dysmenorrhea|pregnancy|labou?r)\b'],
                    'Pediatrics' => ['\bpa?ediatrics?\b', '\b(neonat\w*)\b'],
                    'Orthopedics' => ['\bortho(p(a)?edics?)?\b', '\b(fracture|joint\s+disorders?|metabolic\s+disorder\s+of\s+bone|sports\s+injur)\w*'],
                    'ENT' => ['\bent\b|\botorhinolaryngology\b', '\b(facial\s+nerve|ear|larynx|nose)\b'],
                    'Ophthalmology' => ['\bop(h)?thal(a)?mology\b', '\b(eye|cornea|glaucoma|retina|lens)\b'],
                    'Dermatology' => ['\bderma(tology)?\b', null],
                    'Psychiatry' => ['\bpsychiatry\b', '\b(eating\s+disorders?|schizophrenia|mood\s+disorders?)\b'],
                    'Radiology' => ['\bradiology\b', null],
                    'Anaesthesia' => ['\ban(a)?esthe(s)?i(a|ology)\b', null],
                ],
            ],
            'dental' => [
                'exam_type_ids' => [9],
                'subjects' => [
                    'Oral Pathology' => ['\boral\s+patholog', '\b(white\s+patches|cysts?\s+of\s+the\s+jaw)\b'],
                    'Oral Medicine & Radiology' => ['\boral\s+medicine\b|\bmaxill?i?ofacial\s+radiology\b', null],
                    'Oral & Maxillofacial Surgery' => ['\boral\s+(and\s+maxill?i?ofacial\s+)?surgery\b|\bdento-?alveolar\b|\bexodontia\b', '\bflap\s+design\b'],
                    'Orthodontics' => ['\borthodontics?\b', null],
                    'Periodontics' => ['\bperiodont\w*', null],
                    'Prosthodontics' => ['\bprosthodont\w*', null],
                    'Conservative Dentistry & Endodontics' => ['\bconservative\s+dentistry\b|\bendodont\w*', null],
                    'Pediatric & Preventive Dentistry' => ['\bpa?ediatric\s*(&|and)?\s*preventive\s+dentistry\b|\bpedodont\w*', null],
                    'Public Health Dentistry' => ['\bpublic\s+health\s+dentistry\b|\bcommunity\s+dentistry\b', null],
                    'Oral Biology' => ['\boral\s+biology\b', null],
                    'Dental Materials' => ['\bdental\s+materials?\b', null],
                    'Forensic Odontology' => ['\bforensic\s+odontology\b', null],
                    'Implantology' => ['\bimplantology\b', null],
                    'Anatomy' => ['\banatomy\b', null],
                    'Biochemistry' => ['\bbio-?c?hemistry\b', null],
                    'Pathology' => ['(?<!oral\s)\bpatha?logy\b', null],
                    'Surgery' => ['\bgeneral\s+surgery\b', null],
                    'Medicine' => ['\bgeneral\s+medicine\b', null],
                ],
            ],
            'nursing' => [
                'exam_type_ids' => [5],
                'subjects' => [
                    'Midwifery' => ['\bmid\s*wifery\b', null],
                    'Adult Nursing' => ['\badult\s+nursing\b|\bmedical[\s-]+surgical\s+nursing\b', null],
                    'Child Health Nursing' => ['\bchild\s+health\s+nursing\b|\bpa?ediatric\s+nursing\b', null],
                    'Community Health Nursing' => ['\bcommunity\s+health\s+nursing\b', null],
                    'Mental Health Nursing' => ['\bmental\s+health\s+nursing\b|\bpsychiatric\s+nursing\b', null],
                    'Fundamentals of Nursing' => ['\bfundamentals?\s+of\s+nursing\b', null],
                    'Geriatric Nursing' => ['\bgeriatric\s+nursing\b', null],
                    'Biostatistics' => ['\bbiostatistics\b', null],
                    'Pharmacology' => ['\bpharmacology\b', null],
                ],
            ],
            'entrance' => [
                'exam_type_ids' => [3, 6],
                'subjects' => [
                    'Physics' => ['\bphysics\b', '\b(optics|gravitation|units\s+and\s+dimensions|electrostatics?|current\s+electricity|magnetism|nuclear\s+physics|modern\s+physics|waves?|sound|kinematics|motion|work,?\s+energy|rotational|fluid|elasticity|semiconductor|thermometer|heat\s+and\s+thermodynamics|electrical\s+machines|x-?rays?)\b'],
                    'Chemistry' => ['\bchemistry\b', '\b(hydrocarbons?|organic|inorganic|metallurgy|acid\s+base|hydrogen|alkali\w*|alkaline\s+earth|solid\s+state|chemical\s+bonding|equilibrium|electrochemistry|periodic|reaction\s+mechanism|mole\s+concept|s-block|p-block|d-block)\b'],
                    'Botany' => ['\bbotany\b', '\b(fungi|mycota|bryophytes?|gymnosperms?|angiosperms?|pteridophytes?|algae|photosynthesis|plant\s+(kingdom|physiology|anatomy)|diffusion|osmosis|water\s+potential)\b'],
                    'Zoology' => ['\bzoology\b', '\b(frog|earthworm|cockroach|digestive\s+system|reproductive\s+(system|health)|embryology|animal\s+kingdom|human\s+physiology|circulatory|excretory|nervous\s+system)\b'],
                    'Biology' => ['\bbio(logy)?\b', '\b(cell\s+division|genetics|evolution|ecology)\b'],
                ],
            ],
            'civil' => [
                'exam_type_ids' => [2, 13],
                'subjects' => [
                    'Structural Engineering' => ['\bstructur(al|e)\b', null],
                    'Hydropower' => ['\bhydro\s*power\b', null],
                    'Irrigation & Drainage' => ['\birrigation\b', null],
                    'Water Supply & Sanitation' => ['\bwater\s+supply\b|\bsanitation\b', null],
                    'Transportation Engineering' => ['\btransportation\b|\bhighway\b', null],
                ],
            ],
            'computer' => [
                'exam_type_ids' => [2, 12],
                'subjects' => [
                    'Software Engineering' => ['\bsoftware\s+engineering\b', null],
                    'Artificial Intelligence' => ['\bartificial\s+intelligence\b|\bneural\s+networks?\b', null],
                ],
            ],
            'electrical' => [
                'exam_type_ids' => [2, 14],
                'subjects' => [
                    'Electrical & Electronics Engineering' => ['\belectrical\s+(and|&)\s+electronics?\b', null],
                ],
            ],
        ];
    }

    /**
     * @return array{subject:?string, reason:string} reason: matched | multi_subject | ambiguous | no_match | no_domain
     */
    public static function infer(string $examName, ?int $examTypeId): array
    {
        $name = self::normalise($examName);
        if (preg_match(self::MULTI, $name)) {
            return ['subject' => null, 'reason' => 'multi_subject'];
        }

        $domains = array_filter(self::domains(), fn ($d) => in_array($examTypeId, $d['exam_type_ids'], true));
        if (!$domains) {
            return ['subject' => null, 'reason' => 'no_domain'];
        }

        $explicit = [];
        $topical = [];
        foreach ($domains as $domain) {
            foreach ($domain['subjects'] as $subject => [$exact, $topic]) {
                if (preg_match("/{$exact}/i", $name, $m, PREG_OFFSET_CAPTURE)) {
                    $explicit[$subject] = min($explicit[$subject] ?? PHP_INT_MAX, $m[0][1]);
                } elseif ($topic && preg_match("/{$topic}/i", $name)) {
                    $topical[$subject] = true;
                }
            }
        }

        if (count($explicit) > 1 && ($series = self::seriesSubject($name, $explicit))) {
            return ['subject' => $series, 'reason' => 'matched'];
        }

        $matches = $explicit ?: $topical;
        return match (count($matches)) {
            0 => ['subject' => null, 'reason' => 'no_match'],
            1 => ['subject' => array_key_first($matches), 'reason' => 'matched'],
            default => ['subject' => null, 'reason' => 'ambiguous'],
        };
    }

    /**
     * The subject right after the series prefix wins when it is followed by a
     * "-N-" topic separator, or no list separator comes before the next subject.
     *
     * @param array<string,int> $offsets subject => position of its match
     */
    private static function seriesSubject(string $name, array $offsets): ?string
    {
        asort($offsets);
        [$first, $second] = array_slice(array_keys($offsets), 0, 2);
        preg_match(self::PREFIX, $name, $p);
        if ($offsets[$first] !== strlen($p[0] ?? '')) {
            return null; // the first subject is not in the series position
        }
        $between = substr($name, $offsets[$first], $offsets[$second] - $offsets[$first]);
        $seriesFormat = (bool) preg_match('/^\S+(\s+\S+)?\s*-\s*\d+\s*-/', $between);
        $listed = (bool) preg_match('/,|\band\b|\/|&|\+/i', $between);

        return $seriesFormat || !$listed ? $first : null;
    }

    public static function normalise(string $name): string
    {
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5);
        $name = str_replace(['_', '–', '—'], [' ', '-', '-'], $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }
}
