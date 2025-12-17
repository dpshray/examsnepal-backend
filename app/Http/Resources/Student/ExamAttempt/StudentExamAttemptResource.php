<?php

namespace App\Http\Resources\Student\ExamAttempt;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentExamAttemptResource extends JsonResource
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
            'attempt_id'=>$this->id,
            'exam_id'=>$this->corporate_exam_id,
            'corporate_exam_section_id'=>$this->corporate_exam_section_id,
            'participant_id'=>$this->participant_id,
            'name'=>$this->name,
            'email'=>$this->email,
            'phone'=>$this->phone,
            'attempt_number'=>$this->attempt_number,
            'started_at'=>$this->started_at,
            'status'=>$this->status,
            'total_mark'=>$this->total_mark,
            'obtained_mark'=>$this->obtained_mark,
        ];
    }
}
