<?php

namespace App\Http\Resources\Institute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'syllabus' => $this->syllabus,
            'price' => $this->price !== null ? (float) $this->price : null,
            'duration_days' => $this->duration_days,
            'exams_count' => $this->whenCounted('exams'),
            'notes_count' => $this->whenCounted('notes'),
            'meeting_links_count' => $this->whenCounted('meetingLinks'),
            // Set by the controller from the student's own pivot row; null if they've never applied.
            'my_status' => $this->my_status ?? null,
        ];
    }
}
