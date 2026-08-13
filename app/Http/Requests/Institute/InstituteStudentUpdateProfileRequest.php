<?php

namespace App\Http\Requests\Institute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InstituteStudentUpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $student = Auth::guard('institute_student')->user();

        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('institute_students', 'email')
                    ->where('institute_id', $student->institute_id)
                    ->ignore($student->id),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                Rule::unique('institute_students', 'phone')
                    ->where('institute_id', $student->institute_id)
                    ->ignore($student->id),
            ],
            'password' => 'sometimes|required|string|min:6|confirmed',
        ];
    }
}
