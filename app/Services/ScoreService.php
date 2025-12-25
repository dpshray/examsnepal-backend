<?php

namespace App\Services;

use App\Enums\ExamTypeEnum;
use App\Models\StudentExam;

class ScoreService
{
    function fetchExamScore(StudentExam $student_exam): array {
        $exam = $student_exam->exam;
        $total_answer_count = $exam->questions->count();
        $correct_answer_count = $student_exam->correct_answer_count;
        $incorrect_answer_count = $student_exam->incorrect_answer_count;
        $missed_answer_count = $student_exam->missed_answer_count;

        $is_negative_marking = (bool)$exam->is_negative_marking;

        $missed_answer_count = ($total_answer_count == ($correct_answer_count + $incorrect_answer_count + $missed_answer_count)) ? $missed_answer_count : ($total_answer_count - ($correct_answer_count + $incorrect_answer_count));

        $total_point_reduction_based_on_negative_marking_point = $is_negative_marking ? ($incorrect_answer_count) * $exam->negative_marking_point : 0;


        $scores = [
            'exam_id' => $student_exam->exam->id,
            'exam_name' => $exam->exam_name,
            'type' => ExamTypeEnum::getKeyByValue($student_exam->exam->status),
            'is_negative_marking' => (bool)$is_negative_marking,
            'negative_marking_point' => (float)$exam->negative_marking_point,
            'total_question_count' => (int)$total_answer_count,
            'correct_answer_count' => (int)$correct_answer_count,
            'incorrect_answer_count' => (int)$incorrect_answer_count,
            'missed_answer_count' => (int)$missed_answer_count,
            'total_point_reduction_based_on_negative_marking_point' => $total_point_reduction_based_on_negative_marking_point,
            'final_exam_marks_after_reduction_of_negative_marking_point' => $correct_answer_count - $total_point_reduction_based_on_negative_marking_point
        ];
        return $scores;
    }
}
