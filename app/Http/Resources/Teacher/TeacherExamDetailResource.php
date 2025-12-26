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
            "is_active" => (bool)$this->is_active,
            "exam_type_id" => $this->exam_type_id,
            "assign" => (bool)$this->assign,
            "live" => (bool)$this->live,
            "points_per_question" => (float)$this->points_per_question,
            "is_negative_marking" => (bool)$this->is_negative_marking,
            "negative_marking_point" => (float)$this->negative_marking_point,
            "exam_name" => $this->exam_name,
            "exam_date" => $this->exam_date,
            "exam_time" => $this->exam_time,
            "end_time" => $this->end_time,
            "status" => $this->status,
            "price" => $this->price,
            "payment_st" => $this->payment_st,
            "description" => $this->description,
            "topic" => $this->topic,
            "in_progress" => $this->in_progress,
            "template" => $this->template,
            "remark" => $this->remark,
          
        ];
    }
}
