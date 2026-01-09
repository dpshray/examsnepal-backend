<?php

namespace App\Http\Resources\Corporate\Participant_Group;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorporateGroupResource extends JsonResource
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
            'group_name' => $this->group_name,
            'slug' => $this->slug,
            'created_at' => $this->created_at,
            'total_participant' => $this->participants_count,
        ];
    }
}
