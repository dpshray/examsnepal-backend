<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerExamScoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        $student_exam = $this->student_exams;
        return [
            'id' => $this->whenLoaded('student', fn() => $this->student->id),
            'name' => $this->whenLoaded('student', fn() => $this->student->name),
            'solutions' => $this->whenLoaded('answers', fn() => [
                'corrected' => $this->correct_answers_count, # right answered
                // 'total' => $this->question_count # total questions
            ])
        ];
    }
}
