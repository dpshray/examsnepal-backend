<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherExamDetailResource extends JsonResource
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
            "exam_type_id" => $this->exam_type_id,
            "category_type" => $this->status,
            "exam_name" => $this->exam_name,
            "description" => $this->description,
            "publish" => (bool)$this->is_active,
            "assign" => (bool)$this->assign,
            "live" => (bool)$this->live,
            "is_negative_marking" => (bool)$this->is_negative_marking,
            "negative_marking_point" => (float)$this->negative_marking_point,
            "points_per_question" => (float)$this->points_per_question,
        ];
    }
}
