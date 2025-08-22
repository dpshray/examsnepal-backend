<?php

namespace App\Http\Requests\Corporate;

use Illuminate\Foundation\Http\FormRequest;

class CorporateExamSectionRequest extends FormRequest
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
        $rules = [
            'title' => 'required|max:255',
            'detail' => 'required',
            'is_published' => 'nullable'
        ];
        if (request()->isMethod('POST')) {
            $rules['corporate_exam_id'] = 'required|exists:corporate_exams,id'; 
        }
        return $rules;
    }
}
