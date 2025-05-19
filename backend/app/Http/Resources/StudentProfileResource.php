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
            "id" => (int)$this->id,
            "exam_type_id" => (int)$this->exam_type_id,
            "is_subscripted" => (int)$this->is_subscripted,
            "name" => $this->name,
            "email" => $this->email,
            "email_verified_at" => $this->email_verified_at,
            "image" => $this->image,
            "phone" => $this->phone,
            "address" => $this->address,
            "description" => $this->description,
            "target" => $this->target,
            "college" => $this->college,
            "date" => $this->date,
        ];
    }
}
