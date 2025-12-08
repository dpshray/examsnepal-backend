<?php

namespace App\Http\Requests\Corporate\Question;

use Illuminate\Foundation\Http\FormRequest;

class CorporateQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'question' => 'required|string',
            'description' => 'nullable|string',
            'is_negative_marking' => 'required|boolean',
            'negative_mark' => 'required_if:is_negative_marking,1|numeric',
            'full_marks' => 'required|numeric',
            'question_type' => 'required|in:MCQ,Subjective',
        ];
    }
}
