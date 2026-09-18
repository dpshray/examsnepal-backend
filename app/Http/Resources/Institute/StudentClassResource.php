<?php

namespace App\Http\Resources\Institute;

use App\Http\Resources\Corporate\Classroom\ClassSyllabusTopicResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class StudentClassResource extends JsonResource
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
            'banner' => $this->banner ? Storage::disk('public')->url($this->banner) : null,
            'syllabus_topics' => ClassSyllabusTopicResource::collection($this->whenLoaded('syllabusTopics')),
            'price' => $this->price !== null ? (float) $this->price : null,
            'duration_days' => $this->duration_days,
            'exams_count' => $this->whenCounted('exams'),
            'notes_count' => $this->whenCounted('notes'),
            'meeting_links_count' => $this->whenCounted('meetingLinks'),
            'students_count' => $this->whenCounted('students'),
            // Set by the controller from the student's own pivot row; null if they've never applied.
            'my_status' => $this->my_status ?? null,
        ];
    }
}
