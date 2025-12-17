<?php

namespace App\Http\Resources\Student\Exam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentAllExamScoreDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return $this->student_exams->map(function($student_exam){
            return [
                'exam_name' => $student_exam->exam->exam_name,
                'total_question_count' => $student_exam->total_question_count,
                'correct_answer_count' => $student_exam->correct_answer_count,
            ];
        })->all();
    }
}
