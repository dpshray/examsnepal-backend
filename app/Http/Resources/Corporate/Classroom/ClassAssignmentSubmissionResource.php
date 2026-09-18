<?php

namespace App\Http\Resources\Corporate\Classroom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ClassAssignmentSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => [
                'id' => $this->student?->id,
                'name' => $this->student?->name,
                'email' => $this->student?->email,
            ],
            'type' => $this->type,
            'content_text' => $this->content_text,
            'file_url' => $this->file_path ? Storage::disk('public')->url($this->file_path) : null,
            'annotated_file_url' => $this->annotated_file_path ? Storage::disk('public')->url($this->annotated_file_path) : null,
            'score' => $this->score !== null ? (float) $this->score : null,
            'remark' => $this->remark,
            'graded_at' => $this->graded_at,
            'submitted_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
