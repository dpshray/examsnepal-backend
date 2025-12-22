<?php

namespace App\Http\Resources\Student\Exam;

use App\Enums\ExamTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentAllExamScoreDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return $this->student_exams->map(function($student_exam){
            $exam = $student_exam->exam;
            $total_answer_count = $exam->questions_count;
            $correct_answer_count = $student_exam->correct_answer_count;
            $incorrect_answer_count = $student_exam->incorrect_answer_count;
            $missed_answer_count = $student_exam->missed_answer_count;

            $is_negative_marking = (bool)$exam->is_negative_marking;

            $missed_answer_count = ($total_answer_count == ($correct_answer_count + $incorrect_answer_count + $missed_answer_count)) ? $missed_answer_count : ($total_answer_count - ($correct_answer_count + $incorrect_answer_count));
            
            $total_point_reduction_based_on_negative_marking_point = $is_negative_marking ? ($incorrect_answer_count + $missed_answer_count) * $exam->negative_marking_point : 0;
            return [
                'exam_id' => $student_exam->exam->id,
                'exam_name' => $exam->exam_name,
                'type' => ExamTypeEnum::getKeyByValue($student_exam->exam->status),
                'is_negative_marking' => $is_negative_marking,
                'negative_marking_point' => $exam->negative_marking_point,
                'total_question_count' => (int)$total_answer_count,
                'correct_answer_count' => (int)$correct_answer_count,
                'incorrect_answer_count' => (int)$incorrect_answer_count,
                'missed_answer_count' => (int)$missed_answer_count,
                'total_point_reduction_based_on_negative_marking_point' => $total_point_reduction_based_on_negative_marking_point,
                'final_exam_marks_after_reduction_of_negative_marking_point' => $correct_answer_count - $total_point_reduction_based_on_negative_marking_point
            ];
        })->all();
    }
}
