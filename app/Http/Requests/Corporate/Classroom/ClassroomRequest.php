<?php

namespace App\Http\Requests\Corporate\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class ClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'target' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:2000',
            'price' => 'nullable|numeric|min:0',
            'duration_days' => 'nullable|integer|min:1',
            'syllabus' => 'nullable|string',
        ];
    }
}
