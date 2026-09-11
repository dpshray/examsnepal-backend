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
            'banner' => 'nullable|image|max:4096',
        ];
    }

    /**
     * Multipart form submissions send empty optional fields as empty strings
     * rather than omitting them, which fails rules like numeric/integer.
     */
    protected function prepareForValidation(): void
    {
        $nullableIfEmpty = ['target', 'bio', 'price', 'duration_days'];

        $data = [];
        foreach ($nullableIfEmpty as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $data[$key] = null;
            }
        }

        if ($data) {
            $this->merge($data);
        }
    }
}
