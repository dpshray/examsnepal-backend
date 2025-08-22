<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class TeacherQuestionStoreRequest extends FormRequest
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
            'question' => 'required',
            'option_a' => 'required',
            'option_a_is_true' => 'required|between:0,1',
            'option_b' => 'required',
            'option_b_is_true' => 'required|between:0,1',
            'option_c' => 'required',
            'option_c_is_true' => 'required|between:0,1',
            'option_d' => 'required',
            'option_d_is_true' => 'required|between:0,1',
            'explanation' => 'required',
            'image' => 'sometimes|image',
        ];
    }
}
