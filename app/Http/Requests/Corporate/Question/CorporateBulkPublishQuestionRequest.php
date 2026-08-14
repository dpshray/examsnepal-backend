<?php

namespace App\Http\Requests\Corporate\Question;

use App\Support\DataUriImage;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the admin-edited question list submitted from the bulk-import
 * preview screen before it's written to the database. Unlike the original
 * upload step (which only validates the .docx file itself), this payload is
 * browser-authored JSON and must be validated for real.
 */
class CorporateBulkPublishQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'questions' => 'required|array|min:1',
            'questions.*.text' => 'required|string',
            'questions.*.explanation' => 'required|string',
            'questions.*.image_data_uri' => 'nullable|string',
            'questions.*.options' => 'required|array|size:4',
            // Matches corporate_question_options.option (VARCHAR 255) exactly, so a
            // too-long option fails validation with a clear message instead of the
            // opaque DB-truncation error the old immediate-import path could hit.
            'questions.*.options.*.text' => 'required|string|max:255',
            'questions.*.options.*.is_correct' => 'required|boolean',
            'questions.*.options.*.image_data_uri' => 'nullable|string',
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $questions = $this->input('questions', []);

            foreach ($questions as $index => $question) {
                $options = $question['options'] ?? [];
                $correctCount = count(array_filter($options, fn ($o) => filter_var($o['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN)));

                if ($correctCount !== 1) {
                    $validator->errors()->add(
                        "questions.{$index}.options",
                        'Exactly one option must be marked correct.'
                    );
                }

                if (!empty($question['image_data_uri']) && !DataUriImage::isValid($question['image_data_uri'])) {
                    $validator->errors()->add("questions.{$index}.image_data_uri", 'Invalid image data.');
                }

                foreach ($options as $optIndex => $option) {
                    if (!empty($option['image_data_uri']) && !DataUriImage::isValid($option['image_data_uri'])) {
                        $validator->errors()->add("questions.{$index}.options.{$optIndex}.image_data_uri", 'Invalid image data.');
                    }
                }
            }
        });
    }
}
