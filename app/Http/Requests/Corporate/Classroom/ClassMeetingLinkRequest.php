<?php

namespace App\Http\Requests\Corporate\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class ClassMeetingLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'scheduled_at' => 'nullable|date',
        ];
    }
}
