<?php

namespace App\Http\Resources\Teacher;

use App\Services\ScoreService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherExamSubmissionDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $score = (new ScoreService())->fetchExamScore($this->resource);
        $answersByQuestion = $this->answers->keyBy('question_id');

        $questions = $this->exam->questions->map(function ($question) use ($answersByQuestion) {
            $answer = $answersByQuestion->get($question->id);

            return [
                'id' => $question->id,
                'question' => $question->question,
                'explanation' => $question->explanation,
                'options' => $question->options->map(fn ($option) => [
                    'id' => $option->id,
                    'option' => $option->option,
                    'is_correct_option' => (bool) $option->value,
                    'is_selected' => $answer && (int) $answer->selected_option_id === $option->id,
                ]),
                'is_answered' => (bool) $answer,
                'is_correct' => $answer ? (bool) $answer->is_correct : null,
            ];
        });

        $identity = $this->institute_student_id ? $this->institute_student : $this->student;

        return [
            'id' => $this->id,
            'source' => $this->source,
            'student' => $identity ? [
                'id' => $identity->id,
                'name' => $identity->name,
                'email' => $identity->email,
                'phone' => $identity->phone,
            ] : null,
            'submitted_at' => $this->created_at?->toDateTimeString(),
            'score' => $score,
            'questions' => $questions,
        ];
    }
}
