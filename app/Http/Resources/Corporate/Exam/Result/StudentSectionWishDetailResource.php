<?php

namespace App\Http\Resources\Corporate\Exam\Result;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentSectionWishDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Handle different data structures
        if (is_array($this->resource)) {
            $question = $this->resource['question'];
            $answer = $this->resource['answer'];
            $questionNumber = $this->resource['question_number'];
        } else {
            // Fallback if it's an object
            $question = $this->question;
            $answer = $this->answer;
            $questionNumber = $this->question_number;
        }

        $isMcq = $question->question_type === 'mcq';

        $questionData = [
            'question_number' => $questionNumber,
            'question_id' => $question->id,
            'question' => $question->question,
            'description' => $question->description,
            'question_type' => $question->question_type,
            'full_marks' => (float) $question->full_marks,
            'marks_obtained' => (float) $answer->obtained_mark,
            'is_negative_marking' => (bool) $question->is_negative_marking,
            'negative_mark' => (float) ($question->negative_mark ?? 0),
        ];

        if ($isMcq) {
            // MCQ question
            $questionData['options'] = $question->options->map(function ($option) use ($answer) {
                return [
                    'id' => $option->id,
                    'option' => $option->option,
                    'value' => (bool) $option->value,
                    'is_selected' => $answer->option_id == $option->id,
                ];
            })->values();

            $selectedOption = $answer->option;
            $correctOption = $question->options->where('value', true)->first();

            $questionData['student_answer'] = [
                'option_id' => $answer->option_id,
                'selected_option_text' => $selectedOption ? $selectedOption->option : null,
            ];

            $questionData['correct_answer'] = $correctOption ? [
                'option_id' => $correctOption->id,
                'option_text' => $correctOption->option,
            ] : null;

            $questionData['is_correct'] = $answer->option_id == $correctOption?->id;
        } else {
            // Subjective question
            $questionData['subjective_answer'] = $answer->subjective_answer;
            $questionData['correct_answer'] = null;
            $questionData['is_correct'] = null;
        }

        return $questionData;
    }
}
