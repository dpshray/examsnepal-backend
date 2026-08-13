<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstituteStudentRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $instituteId = $this->route('institute')?->id;

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('institute_students', 'email')->where('institute_id', $instituteId),
            ],
            'phone' => [
                'nullable',
                'string',
                Rule::unique('institute_students', 'phone')->where('institute_id', $instituteId),
            ],
            'password' => 'required|string|min:6|confirmed',
        ];
    }
}
