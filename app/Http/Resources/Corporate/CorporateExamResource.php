<?php

namespace App\Http\Resources\Corporate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorporateExamResource extends JsonResource
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
            "id" => $this->id,
            "title" => $this->title,
            "slug"=>$this->slug,
            "exam_date" => $this->exam_date,
            "start_time" => $this->start_time,
            "end_time" => $this->end_time,
            "description" => $this->description,
            "instructions" => $this->instructions,
            "is_published" => (bool)$this->is_published,
            "duration" => $this->duration,
            "is_shuffled_question" => $this->is_shuffled_question,
            "is_shuffled_option" => $this->is_shuffled_option,
            "attempts" => $this->limit_attempts ? true : false,
            "limit_attempts" => $this->limit_attempts,
            "participant_count" => $this->whenCounted('participants'),
            "section_count" => $this->whenCounted('sections'),
            "question_count" => $this->whenCounted('questions'),
            "exam_type" => $this->exam_type,
        ];
    }
}
