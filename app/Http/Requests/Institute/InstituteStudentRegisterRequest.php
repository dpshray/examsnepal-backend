<?php

namespace App\Http\Requests\Institute;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstituteStudentRegisterRequest extends FormRequest
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
    }

    public function rules(): array
    {
        $institute = $this->route('institute');
        $instituteId = is_object($institute)
            ? $institute->id
            : User::where('slug', $institute)->orWhere('username', $institute)->value('id');

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('institute_students', 'email')->where(function ($query) use ($instituteId) {
                    return $query->where('institute_id', $instituteId);
                }),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('institute_students', 'phone')->where(function ($query) use ($instituteId) {
                    return $query->where('institute_id', $instituteId)->whereNotNull('phone')->where('phone', '!=', '');
                }),
            ],
            'password' => 'required|string|min:6|confirmed',
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
            'password.required' => 'Please provide a password.',
            'password.min' => 'Password must be at least 6 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}

