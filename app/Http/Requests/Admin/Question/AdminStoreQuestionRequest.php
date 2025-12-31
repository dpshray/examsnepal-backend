<?php

namespace App\Http\Requests\Admin\Question;

use Illuminate\Foundation\Http\FormRequest;

class AdminStoreQuestionRequest extends FormRequest
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
            "question" => 'required',
            "explanation" => 'required',
            "option_d_id" => 'required|exists:option_questions,id',
            "option_c_id" => 'required|exists:option_questions,id',
            "option_b_id" => 'required|exists:option_questions,id',
            "option_a_id" => 'required|exists:option_questions,id',
            "option_a" => 'required|max:255',
            "option_b" => 'required|max:255',
            "option_c" => 'required|max:255',
            "option_d" => 'required|max:255',
            "image" => 'sometimes|nullable|image',
            "option_a_is_true" => 'required|boolean',
            "option_b_is_true" => 'required|boolean',
            "option_c_is_true" => 'required|boolean',
            "option_d_is_true" => 'required|boolean',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'option_a_is_true' => filter_var($this->option_a_is_true, FILTER_VALIDATE_BOOLEAN) ? true : false,
            'option_b_is_true' => filter_var($this->option_b_is_true, FILTER_VALIDATE_BOOLEAN) ? true : false,
            'option_c_is_true' => filter_var($this->option_c_is_true, FILTER_VALIDATE_BOOLEAN) ? true : false,
            'option_d_is_true' => filter_var($this->option_d_is_true, FILTER_VALIDATE_BOOLEAN) ? true : false,
        ]);
    }
}
