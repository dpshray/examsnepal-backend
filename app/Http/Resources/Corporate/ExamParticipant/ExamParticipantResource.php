<?php

namespace App\Http\Resources\Corporate\ExamParticipant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamParticipantResource extends JsonResource
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
            'student_id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'pexam_id' => $this->when(
                isset($this->pivot) && isset($this->pivot->id),
                $this->pivot->id
            ),
        ];
    }
}
