<?php

namespace App\Http\Resources\Student\Exam;

use App\Enums\ExamTypeEnum;
use App\Services\ScoreService;
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
            return (new ScoreService)->fetchExamScore($student_exam);
        })->all();
    }
}
