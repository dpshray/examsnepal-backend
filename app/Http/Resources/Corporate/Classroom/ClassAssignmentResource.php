<?php

namespace App\Http\Resources\Corporate\Classroom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ClassAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'content_text' => $this->content_text,
            'file_url' => $this->file_path ? Storage::disk('public')->url($this->file_path) : null,
            'full_marks' => (float) $this->full_marks,
            'submissions_count' => $this->whenCounted('submissions'),
            'created_at' => $this->created_at,
        ];
    }
}
