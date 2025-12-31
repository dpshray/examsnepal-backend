<?php

namespace App\Services;

use App\Models\Corporate\CorporateExam;
use Illuminate\Support\Collection;

class CorporateExamResultService
{
    private $exam;
    private $attempts;

    public function __construct(CorporateExam $exam, Collection $attempts)
    {
        $this->exam = $exam;
        $this->attempts = $attempts;
    }

    public function getRankedResults()
    {
        // Group by participant
        $groupedAttempts = $this->groupAttemptsByParticipant();

        // Calculate results
        $results = $this->calculateResults($groupedAttempts);

        // Sort by total marks
        $sortedResults = $this->sortByTotalMarks($results);

        // Assign ranks
        $rankedResults = $this->assignRanks($sortedResults);

        // Get section totals
        $sectionTotals = $this->getSectionTotalMarks();

        return [
            'exam' => [
                'id' => $this->exam->id,
                'title' => $this->exam->title,
                'exam_date' => $this->exam->exam_date?->format('Y-m-d'),
                'total_participants' => count($rankedResults),
            ],
            'section_total_marks' => $sectionTotals,
            'results' => $rankedResults,
            'statistics' => $this->calculateStatistics($rankedResults),
        ];
    }

    private function groupAttemptsByParticipant()
    {
        return $this->attempts->groupBy(function($attempt) {
            if ($this->exam->exam_type === 'public') {
                return $attempt->email;
            }
            return $attempt->participant_id;
        });
    }

    private function calculateResults($groupedAttempts)
    {
        $results = [];

        foreach ($groupedAttempts as $key => $participantAttempts) {
            $firstAttempt = $participantAttempts->first();

            // Calculate section-wise marks
            $sectionMarks = [];
            $totalMarks = 0;

            foreach ($participantAttempts as $attempt) {
                $sectionMarks[$attempt->section->title] = [
                    'marks' => (float) $attempt->obtained_mark,
                    'attempt_id' => $attempt->id,
                    'submitted_at' => $attempt->submitted_at?->format('Y-m-d H:i:s'),
                ];
                $totalMarks += (float) $attempt->obtained_mark;
            }

            $results[] = [
                'participant_id' => $firstAttempt->participant_id,
                'name' => $firstAttempt->name,
                'email' => $firstAttempt->email,
                'phone' => $firstAttempt->phone ?? 'N/A',
                'section_wise_marks' => $sectionMarks,
                'total_marks' => round($totalMarks, 2),
            ];
        }

        return $results;
    }

    private function sortByTotalMarks($results)
    {
        usort($results, function($a, $b) {
            return $b['total_marks'] <=> $a['total_marks'];
        });

        return $results;
    }

    private function assignRanks($results)
    {
        $rankedResults = [];
        $previousMarks = null;
        $rank = 1;
        $sameRankCount = 0;

        foreach ($results as $index => $result) {
            if ($previousMarks !== null && $result['total_marks'] < $previousMarks) {
                $rank += $sameRankCount;
                $sameRankCount = 1;
            } else {
                $sameRankCount++;
            }

            $result['rank'] = $rank;
            $previousMarks = $result['total_marks'];
            $rankedResults[] = $result;
        }

        return $rankedResults;
    }

    private function getSectionTotalMarks()
    {
        $sectionTotals = [];

        foreach ($this->exam->sections as $section) {
            $sectionTotals[$section->title] = (float) $section->questions()->sum('full_marks');
        }

        return $sectionTotals;
    }

    private function calculateStatistics($rankedResults)
    {
        $totalMarks = array_column($rankedResults, 'total_marks');

        return [
            'average_score' => round(array_sum($totalMarks) / count($totalMarks), 2),
            'highest_score' => max($totalMarks),
            'lowest_score' => min($totalMarks),
            // 'median_score' => $this->calculateMedian($totalMarks),
        ];
    }

    private function calculateMedian($array)
    {
        sort($array);
        $count = count($array);
        $middle = floor(($count - 1) / 2);

        if ($count % 2) {
            return $array[$middle];
        }

        return ($array[$middle] + $array[$middle + 1]) / 2;
    }
}
