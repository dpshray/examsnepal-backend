<?php

namespace App\Services\Marketing;

use App\Services\ScoreService;
use Illuminate\Support\Facades\DB;

/**
 * Writes student_exams.score_pct (and submitted_at when missing) for
 * completed attempts, with the same marking rules the student sees.
 */
class AttemptScorer
{
    /** @param int[] $studentExamIds */
    public function score(array $studentExamIds): int
    {
        if (!$studentExamIds) {
            return 0;
        }

        $attempts = DB::table('student_exams')
            ->whereIn('id', $studentExamIds)
            ->where('is_exam_completed', 1)
            ->get(['id', 'exam_id', 'submitted_at', 'updated_at', 'created_at']);
        if ($attempts->isEmpty()) {
            return 0;
        }

        $examIds = $attempts->pluck('exam_id')->unique()->all();
        $exams = DB::table('exams')->whereIn('id', $examIds)
            ->get(['id', 'points_per_question', 'is_negative_marking', 'negative_marking_point'])
            ->keyBy('id');
        $questionCounts = DB::table('questions')->whereIn('exam_id', $examIds)
            ->groupBy('exam_id')->pluck(DB::raw('COUNT(*)'), 'exam_id');
        $answers = DB::table('answersheets')
            ->whereIn('student_exam_id', $attempts->pluck('id')->all())
            ->groupBy('student_exam_id')
            ->get([
                'student_exam_id',
                DB::raw('SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct'),
                DB::raw('SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) as incorrect'),
            ])
            ->keyBy('student_exam_id');

        $updated = 0;
        foreach ($attempts as $attempt) {
            $exam = $exams->get($attempt->exam_id);
            $counts = $answers->get($attempt->id);
            $pct = $exam ? ScoreService::percentage(
                (int) ($counts->correct ?? 0),
                (int) ($counts->incorrect ?? 0),
                (int) ($questionCounts[$attempt->exam_id] ?? 0),
                (float) $exam->points_per_question,
                (bool) $exam->is_negative_marking,
                (float) $exam->negative_marking_point,
            ) : null;

            DB::table('student_exams')->where('id', $attempt->id)->update([
                'score_pct' => $pct,
                'submitted_at' => $attempt->submitted_at ?? $attempt->updated_at ?? $attempt->created_at,
            ]);
            $updated++;
        }
        return $updated;
    }
}
