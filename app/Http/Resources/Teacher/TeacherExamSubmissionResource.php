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

        $identity = $this->institute_student_id ? $this->institute_student : $this->student;

        return [
            'id' => $this->id,
            'source' => $this->source,
            'student' => $identity ? [
                'id' => $identity->id,
                'name' => $identity->name,
                'email' => $identity->email,
                'phone' => $identity->phone,
            ] : null,
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
