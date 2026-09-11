<?php

namespace App\Http\Resources\Teacher;

use App\Enums\ExamTypeEnum;
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
            "published" => $this->is_active,
            "exam_type" => $this->whenLoaded('examType'),
            "category_type" => [
                'id' => $this->status,
                'name' => ExamTypeEnum::getKeyByValue($this->status),
            ],
            "exam_tags" => $this->whenLoaded('examTags', function () {
                return $this->examTags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug,
                    ];
                });
            }),
            "exam_name" => $this->exam_name,
            "is_class_exam" => (bool)$this->is_class_exam,
            "exam_mode" => $this->exam_mode,
            "exam_date" => $this->exam_date,
            "exam_time" => $this->exam_time,
            "end_time" => $this->end_time,
            "instructions" => $this->instructions,
            "live" => $this->live,
            "description" => $this->description,
            "assign" => $this->assign,
            'total_questions' => $this->whenCounted('questions'),
            "is_negative_marking" => (bool)$this->is_negative_marking,
            "negative_marking_point" => (float)$this->negative_marking_point,
            "points_per_question" => (float)$this->points_per_question,
            "is_shuffled_question" => (bool)$this->is_shuffled_question,
            "is_shuffled_option" => (bool)$this->is_shuffled_option,
            'duration' => $this->minToHis()
        ];
    }
}
