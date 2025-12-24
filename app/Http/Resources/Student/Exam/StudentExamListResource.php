<?php

namespace App\Http\Resources\Student\Exam;

use App\Enums\ExamTypeEnum;
use App\Http\Resources\PlayerExamScoreCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;


class StudentExamListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        $status = null;
        if (!empty($this->status)) {
            $raw = ExamTypeEnum::getKeyByValue($this->status);
            $status = explode('_', strtolower($raw))[0];
        }
        return [
            "id" => $this->id,
            "exam_name" => $this->exam_name,
            'is_negative_marking' => (bool)$this->is_negative_marking,
            'negative_marking_point' => (float)$this->negative_marking_point,
            "status" =>  $status,
            "questions_count" => $this->whenCounted('questions', fn() => (int) $this->questions_count),
            "user" => $this->whenLoaded('user'), #<---added_by
            // 'players' => $this->whenLoaded('student_exams', fn() => new PlayerExamScoreCollection($this->student_exams))
            'players' => $this->student_exams->map(
                fn($SE) =>
                [
                    'id' => $SE->student->id,
                    'name' => $SE->student->name,
                    'solutions' => [
                        'corrected' => (int)$SE->correct_answers_count, # right answered
                        'total' => (int)$this->questions_count # total questions
                    ]
                ]
            )
        ];
    }
}
