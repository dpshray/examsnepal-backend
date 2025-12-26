<?php

namespace App\Http\Resources\Corporate\Exam\Submission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllSubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return [
            'id' => $this->id,
            'attempt_number' => $this->attempt_number,

            // Student/Participant Info
            'student' => [
                'id' => $this->participant_id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
            ],

            // Exam Info
            'exam' => [
                'id' => $this->exam->id,
                'title' => $this->exam->title,
                'exam_type' => $this->exam->exam_type,
                'duration' => $this->exam->duration,
            ],

            // Section Info
            'section' => [
                'id' => $this->section->id,
                'title' => $this->section->title,
            ],

            // Timing
            'started_at' => $this->started_at?->format('Y-m-d H:i:s'),
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),

            // Status
            'status' => $this->status,

            // Marks
            'total_mark' => (float) $this->total_mark,
            'obtained_mark' => (float) $this->obtained_mark,
            'percentage' => $this->total_mark > 0
                ? round(($this->obtained_mark / $this->total_mark) * 100, 2)
                : 0,
        ];
    }
}
