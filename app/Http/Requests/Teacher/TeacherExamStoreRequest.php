<?php

namespace App\Http\Requests\Teacher;

use App\Enums\ExamTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherExamStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exam_type_id' => 'required|exists:exam_types,id',
            'category_type' => ['required', Rule::enum(ExamTypeEnum::class)],
            'exam_name' => 'required',
            'description' => 'required',
            'publish' => 'required|between:0,1',
            'assign' => 'required|between:0,1',
            'live' => 'required|between:0,1',
            'is_negative_marking' => 'required|between:0,1',
            'negative_marking_point' => [Rule::requiredIf($this->is_negative_marking == 1)],
            'points_per_question' => 'required|numeric|min:1',
            'duration' => 'sometimes|nullable|integer',
        ];
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'duration' => $this->minToHis($this->duration),
        ]);
    }

    private function minToHis($minutes){
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%02d:%02d:00', $hours, $remainingMinutes);
    }
}
