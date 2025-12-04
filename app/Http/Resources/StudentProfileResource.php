<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentProfileResource extends JsonResource
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
            "id" => $this->id,
            "exam_type_id" => $this->exam_type_id,
            "is_subscripted" => $this->is_subscripted,
            "name" => $this->name,
            "email" => $this->email,
        ];
    }
}
