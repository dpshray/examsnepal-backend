<?php

namespace App\Http\Resources\Corporate\Classroom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassStudentResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->pivot->status,
            'joined_at' => $this->pivot->created_at,
        ];
    }
}
