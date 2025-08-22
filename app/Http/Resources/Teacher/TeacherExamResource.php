<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherExamResource extends JsonResource
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
            "publised" => $this->is_active,
            "exam_type" => $this->whenLoaded('examType'),
            "exam_name" => $this->exam_name,
            'total_questions' => $this->whenCounted('questions')
        ];
    }
}
