<?php

namespace App\Http\Requests\Corporate\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class ClassStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
        ];
    }
}
