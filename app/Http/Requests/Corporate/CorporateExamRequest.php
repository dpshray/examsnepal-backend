<?php

namespace App\Http\Requests\Corporate;

use Illuminate\Foundation\Http\FormRequest;

class CorporateExamRequest extends FormRequest
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
            'title' => 'required|max:255',
            'exam_date' => 'nullable|date_format:Y-m-d',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'is_published' => 'required|in:0,1',
            'description' => 'nullable|sometimes|string',
            'instructions' => 'nullable|sometimes|string',
            'duration' => 'nullable|sometimes|integer',
            'is_shuffled_question' => 'nullable|sometimes|boolean',
            'is_shuffled_option' => 'nullable|sometimes|boolean',
            'limit_attempts' => 'nullable|sometimes|integer',
        ];
    }
}
