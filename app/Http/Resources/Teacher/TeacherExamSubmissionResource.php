<?php

namespace App\Http\Resources\Teacher;

use App\Services\ScoreService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherExamSubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $score = (new ScoreService())->fetchExamScore($this->resource);

        return [
            'id' => $this->id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'email' => $this->student->email,
                'phone' => $this->student->phone,
            ]),
            'submitted_at' => $this->created_at?->toDateTimeString(),
            'total_question_count' => $score['total_question_count'],
            'correct_answer_count' => $score['correct_answer_count'],
            'incorrect_answer_count' => $score['incorrect_answer_count'],
            'missed_answer_count' => $score['missed_answer_count'],
            'marks' => $score['final_exam_marks_after_reduction_of_negative_marking_point'],
            'full_marks' => $score['full_marks'],
            'is_negative_marking' => $score['is_negative_marking'],
        ];
    }
}
