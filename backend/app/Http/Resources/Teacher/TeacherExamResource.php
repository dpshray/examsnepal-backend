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
            "category_type"=> [
                'id'=>$this->status,
                'name'=>ExamTypeEnum::getKeyByValue($this->status),
            ],
            "exam_name" => $this->exam_name,
            "live"=>$this->live,
            "description"=>$this->description,
            "assign"=>$this->assign,
            'total_questions' => $this->whenCounted('questions')
        ];
    }
}
