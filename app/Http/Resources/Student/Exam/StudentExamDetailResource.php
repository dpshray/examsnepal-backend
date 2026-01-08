<?php

namespace App\Http\Resources\Student\Exam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class StudentExamDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return [
            'exam_id' => $this->id,
            "title" => $this->title,
            "slug" => $this->slug,
            "exam_date" => $this->exam_date,
            "start_time" => $this->start_time,
            "end_time" => $this->end_time,
            "description" => $this->description,
            "instructions" => $this->instructions,
            "duration" => $this->duration,
            "limit_attempts" => $this->limit_attempts,
            "exam_type" => $this->exam_type,
            'sections' => $this->sections->map(function ($section) {
                $completedSections = $this->completedSections ?? [];
                $isCompleted = (bool)in_array($section->id, $completedSections);
                Log::info($completedSections);
                Log::info($section->id);
                Log::info($this->completedSections);
                return [
                    "id" => $section->id,
                    "title" => $section->title,
                    "slug" => $section->slug,
                    "detail" => $section->detail,
                    'is_completed' => $isCompleted,
                ];
            }),

        ];
    }
}
