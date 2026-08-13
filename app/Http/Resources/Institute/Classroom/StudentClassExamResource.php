<?php

namespace App\Http\Resources\Institute\Classroom;

use App\Models\StudentExam;
use App\Services\ScoreService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentClassExamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StudentExam|null $attempt */
        $attempt = $this->my_attempt ?? null;

        $status = 'not_started';
        $score = null;
        if ($attempt) {
            $status = $attempt->is_exam_completed ? 'completed' : 'in_progress';
            if ($attempt->is_exam_completed) {
                $score = (new ScoreService())->fetchExamScore($attempt);
            }
        }

        return [
            'id' => $this->id,
            'exam_name' => $this->exam_name,
            'description' => $this->description,
            'total_questions' => $this->whenCounted('questions'),
            'duration' => $this->minToHis(),
            'is_negative_marking' => (bool) $this->is_negative_marking,
            'negative_marking_point' => (float) $this->negative_marking_point,
            'points_per_question' => (float) $this->points_per_question,
            'status' => $status,
            'score' => $score,
        ];
    }
}
