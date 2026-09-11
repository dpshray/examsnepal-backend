<?php

namespace App\Http\Resources\Institute\Classroom;

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
        return [
            'id' => $this->id,
            'exam_name' => $this->exam_name,
            'is_class_exam' => (bool) $this->is_class_exam,
            'exam_mode' => $this->exam_mode,
            'exam_date' => $this->exam_date,
            'exam_time' => $this->exam_time,
            'end_time' => $this->end_time,
            'is_open_now' => $this->isWithinScheduledWindow(),
            'instructions' => $this->instructions,
            'description' => $this->description,
            'total_questions' => $this->total_questions ?? $this->whenCounted('questions'),
            'duration' => $this->minToHis(),
            'is_negative_marking' => (bool) $this->is_negative_marking,
            'negative_marking_point' => (float) $this->negative_marking_point,
            'points_per_question' => (float) $this->points_per_question,
            'status' => $this->my_status ?? 'not_started',
            'score' => $this->my_score ?? null,
        ];
    }
}
