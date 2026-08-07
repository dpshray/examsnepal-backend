<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherDoubtResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status ? 'Not Resolved' : 'Resolved',
            'doubt' => $this->doubt,
            'date' => $this->date ?? $this->created_at,
            'remark' => $this->remark,
            'question' => [
                'question' => $this->question->question ?? null,
                'options' => $this->question->options ?? null,
                'explanation' => $this->question->explanation ?? null,
            ],
            'exam_name' => $this->question->exam->exam_name ?? null,
            'student' => [
                'name' => $this->student->name ?? null,
            ],
        ];
    }
}
