<?php

namespace App\Http\Resources\Corporate\Classroom;

use App\Models\Corporate\ClassExamQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassExamQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->class_exam_section_id,
            'question_type' => $this->question_type,
            'question' => $this->question,
            'description' => $this->description,
            'full_marks' => (float) $this->full_marks,
            'is_negative_marking' => (bool) $this->is_negative_marking,
            'negative_mark' => $this->negative_mark !== null ? (float) $this->negative_mark : null,
            'image_url' => $this->getFirstMediaUrl(ClassExamQuestion::QUESTION_IMAGE) ?: null,
            'options' => $this->when(
                $this->question_type === 'mcq',
                fn () => $this->options->map(fn ($option) => [
                    'id' => $option->id,
                    'option' => $option->option,
                    'value' => $option->value,
                    'image_url' => $option->image_url,
                ])
            ),
            'created_at' => $this->created_at,
        ];
    }
}
