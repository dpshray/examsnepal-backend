<?php

namespace App\Services;

use App\Models\StudentExam;

/**
 * Scores a sectioned class-exam attempt (Corporate\ClassExamAnswer rows).
 * Ungraded subjective answers contribute 0 toward marks_obtained but are
 * flagged via pending_grading so the UI can show the score is provisional.
 */
class ClassExamScoreService
{
    public function compute(StudentExam $attempt): array
    {
        $exam = $attempt->exam()->with('classExamSections.questions')->first();
        $questions = $exam->classExamSections->flatMap->questions;
        $answers = $attempt->classExamAnswers()->get()->keyBy('class_exam_question_id');

        $fullMarks = (float) $questions->sum('full_marks');
        $marksObtained = 0.0;
        $pendingGrading = false;

        foreach ($questions as $question) {
            $answer = $answers->get($question->id);
            if ($question->question_type === 'subjective' && (!$answer || $answer->graded_at === null)) {
                $pendingGrading = true;
                continue;
            }
            $marksObtained += (float) ($answer->marks_obtained ?? 0);
        }

        return [
            'full_marks' => $fullMarks,
            'marks_obtained' => round($marksObtained, 2),
            'total_questions' => $questions->count(),
            'pending_grading' => $pendingGrading,
        ];
    }
}
