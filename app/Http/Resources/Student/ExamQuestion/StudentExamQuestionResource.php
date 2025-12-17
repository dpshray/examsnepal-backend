<?php

namespace App\Http\Resources\Student\ExamQuestion;

use App\Models\Corporate\CorporateQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentExamQuestionResource extends JsonResource
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
            'section_id' => $this->corporate_exam_section_id,
            'question_type' => $this->question_type,
            'question' => $this->question,
            'description' => $this->description,
            'full_marks' => $this->full_marks,
            'negative_marks' => $this->negative_mark,
            'is_negative_marking' => (bool)$this->is_negative_marking,
            'image_url' => $this->getFirstMediaUrl(CorporateQuestion::QUESTION_IMAGE) ?: null,
            'options' => $this->when(
                strtolower($this->question_type) === 'mcq',
                function () {
                    return $this->options?->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'option' => $option->option,
                            // 'value' => $option->value,
                        ];
                    }) ?? [];
                }
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
