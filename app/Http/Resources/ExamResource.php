<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\PlayerExamScoreCollection;

class ExamResource extends JsonResource
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
            "exam_name" => $this->exam_name,
            "status" => $this->status,
            "questions_count" => $this->whenCounted('questions', fn() => (int) $this->questions_count),
            "user" => $this->whenLoaded('user'), #<---added_by
            'players' => $this->whenLoaded('student_exams', fn() => new PlayerExamScoreCollection($this->student_exams))
        ];
    }
}
