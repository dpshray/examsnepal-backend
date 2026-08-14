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

    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && trim((string) $this->phone) === '') {
            $this->merge(['phone' => null]);
        }
        if ($this->has('password') && trim((string) $this->password) === '') {
            $this->request->remove('password');
            $this->request->remove('password_confirmation');
        }
    }

    public function rules(): array
    {
        $student = Auth::guard('institute_student')->user();
        $instituteId = $student?->institute_id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('institute_students', 'email')
                    ->where(function ($query) use ($instituteId) {
                        return $query->where('institute_id', $instituteId);
                    })
                    ->ignore($student?->id),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('institute_students', 'phone')
                    ->where(function ($query) use ($instituteId) {
                        return $query->where('institute_id', $instituteId)->whereNotNull('phone')->where('phone', '!=', '');
                    })
                    ->ignore($student?->id),
            ],
            'password' => 'sometimes|nullable|string|min:6|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your full name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'A student with this email is already registered for this institute.',
            'phone.unique' => 'A student with this phone number is already registered for this institute.',
            'password.min' => 'Password must be at least 6 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}

