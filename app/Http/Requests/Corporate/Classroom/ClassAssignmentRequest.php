<?php

namespace App\Http\Requests\Corporate\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class ClassAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'type' => 'required|in:pdf,image,text',
            'content_text' => 'required_if:type,text|nullable|string',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,gif|max:10240',
            'full_marks' => 'required|numeric|min:0',
        ];
    }
}
