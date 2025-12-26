<?php

namespace App\Http\Resources;

use App\Services\ScoreService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class PlayerExamScoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        // $student_exam = $this->student_exams;
        [
            "correct_answer_count" => $correct_answer_count,
            'is_negative_marking' => $is_negative_marking,
            'negative_marking_point' => $negative_marking_point,
            'incorrect_answer_count' => $incorrect_answer_count,
            'missed_answer_count' => $missed_answer_count,
            'total_point_reduction_based_on_negative_marking_point' => $total_point_reduction_based_on_negative_marking_point,
            // 'final_exam_marks_after_reduction_of_negative_marking_point' => $correct_answer_count - $total_point_reduction_based_on_negative_marking_point,
            'correct_marking_point' => $points_per_question,
            'full_marks' => $full_marks

        ] = (new ScoreService())->fetchExamScore($this->resource);
        // Log::info($this->resource);
        return [
            'id' => $this->whenLoaded('student', fn() => $this->student->id),
            'name' => $this->whenLoaded('student', fn() => $this->student->name),
            'solutions' => [
                'marks' => (float)(($correct_answer_count * $points_per_question) - $total_point_reduction_based_on_negative_marking_point), # right answered
                'full_marks' => $full_marks, # total questions,
                'correct_marking_point' => (float)$points_per_question,
                'correct_answer_count' => $correct_answer_count,
                'is_negative_marking' => $is_negative_marking,
                'negative_marking_point' => $negative_marking_point,
                'incorrect_answer_count' => $incorrect_answer_count,
                'missed_answer_count' => $missed_answer_count,
                'total_point_reduction_based_on_negative_marking_point' => $total_point_reduction_based_on_negative_marking_point,
            ],
            // 'corrected' => $this->whenCounted('correct_answers')
        ];
    }
}
