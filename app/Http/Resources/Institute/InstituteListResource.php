<?php

namespace App\Http\Resources\Institute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstituteListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'username' => $this->username,
            'fullname' => $this->fullname,
            'org' => $this->org,
            'logo' => $this->logoUrl(),
            'location' => $this->location,
            'students_count' => (int) ($this->students_count ?? 0),
            'average_rating' => $this->average_rating !== null ? round((float) $this->average_rating, 1) : null,
        ];
    }
}
