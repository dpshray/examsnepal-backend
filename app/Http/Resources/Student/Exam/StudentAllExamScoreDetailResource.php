<?php

namespace App\Http\Resources\Student\Exam;

use App\Enums\ExamTypeEnum;
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
                // 'exam_id' => $student_exam->exam->id,
                'exam_name' => $student_exam->exam->exam_name,
                'type' => ExamTypeEnum::getKeyByValue($student_exam->exam->status),
                'total_question_count' => $student_exam->exam->questions_count,
                'correct_answer_count' => $student_exam->correct_answer_count,
            ];
        })->all();
    }
}
