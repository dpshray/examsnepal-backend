<?php

namespace App\Http\Requests\Corporate\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class ClassExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => 'required|string',
            'description' => 'nullable|string',
            'question_type' => 'required|in:mcq,subjective',
            'full_marks' => 'required|numeric|min:0',
            'is_negative_marking' => 'required|boolean',
            'negative_mark' => 'required_if:is_negative_marking,1|nullable|numeric|min:0',
            'options' => 'required_if:question_type,mcq|array|min:2|max:5',
            'options.*.option' => 'required_with:options|string',
            'options.*.value' => 'required_with:options|boolean',
            'image' => 'nullable|image|max:2048',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_negative_marking')) {
            $this->merge([
                'is_negative_marking' => filter_var($this->is_negative_marking, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            ]);
        }

        if ($this->input('negative_mark') === '') {
            $this->merge(['negative_mark' => null]);
        }
    }
}
