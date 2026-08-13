<?php

namespace App\Http\Resources\Corporate\Classroom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ClassNoteResource extends JsonResource
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
            'title' => $this->title,
            'type' => $this->type,
            'content' => $this->content,
            'file_url' => $this->file_path ? Storage::disk('public')->url($this->file_path) : null,
            'video_url' => $this->video_url,
            'created_at' => $this->created_at,
        ];
    }
}
