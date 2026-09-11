<?php

namespace App\Http\Requests\Corporate\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class ClassAssignmentGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $submission = $this->route('submission');
        $fullMarks = $submission?->assignment?->full_marks ?? PHP_INT_MAX;

        return [
            'score' => "required|numeric|min:0|max:{$fullMarks}",
            'remark' => 'nullable|string|max:2000',
        ];
    }
}
