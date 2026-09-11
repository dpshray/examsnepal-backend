<?php

namespace App\Http\Resources\Corporate\Classroom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ClassResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'target' => $this->target,
            'bio' => $this->bio,
            'price' => $this->price !== null ? (float) $this->price : null,
            'duration_days' => $this->duration_days,
            'banner' => $this->banner ? Storage::disk('public')->url($this->banner) : null,
            'syllabus_topics' => ClassSyllabusTopicResource::collection($this->whenLoaded('syllabusTopics')),
            'notes_count' => $this->whenCounted('notes'),
            'exams_count' => $this->whenCounted('exams'),
            'meeting_links_count' => $this->whenCounted('meetingLinks'),
            'enrolled_count' => $this->when(isset($this->enrolled_count), fn () => (int) $this->enrolled_count),
            'pending_count' => $this->when(isset($this->pending_count), fn () => (int) $this->pending_count),
            'created_at' => $this->created_at,
        ];
    }
}
