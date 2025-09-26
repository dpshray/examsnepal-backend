<?php

namespace App\Http\Resources\Submission;

use App\Enums\ExamTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        $score = $this->answers ? $this->answers->where('is_correct', 1)->count() : 0;

        return [
            'id'=>$this->student->id,
            'student_name'  => $this->student->name ?? null,
            'student_email' => $this->student->email ?? null,
            'exam_name'     => $this->exam->exam_name ?? null,
            'exam_type'     => $this->exam->examType->name ?? null,
            'exam_category' =>ExamTypeEnum::getKeyByValue($this->exam->status) ,
            'score'         => $score,
        ];
    }
}
