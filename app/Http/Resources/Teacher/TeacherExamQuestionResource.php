<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherExamQuestionResource extends JsonResource
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
            'id' => $this->id,
            'question' => $this->question,
            'explanation' => $this->explanation,
            'options' => $this->options->map(fn($option) => [
                "id" => $option->id,
                "question_id" => $option->question_id,
                "option" => $option->option,
                "value" => (bool)$option->value,
            ])
        ];
    }
}
