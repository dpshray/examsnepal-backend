<?php

namespace App\Http\Requests\Corporate\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class ClassNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'type' => 'required|in:pdf,video_link',
            'content' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf|max:10240',
            'video_url' => 'nullable|url|max:2048',
        ];
    }
}
