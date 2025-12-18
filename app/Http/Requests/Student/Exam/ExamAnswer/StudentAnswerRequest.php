<?php

namespace App\Http\Requests\Student\Exam\ExamAnswer;

use Illuminate\Foundation\Http\FormRequest;

class StudentAnswerRequest extends FormRequest
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
            'answer'=>'required|array',
            'answer.*.question_id'=>'required|exists:corporate_questions,id',
            'answer.*.option_id' => 'nullable|exists:corporate_question_options,id',
            'answer.*.subjective_answer' => 'nullable|string',
        ];
    }
}
