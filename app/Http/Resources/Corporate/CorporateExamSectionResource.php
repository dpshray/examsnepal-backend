<?php

namespace App\Http\Resources\Corporate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorporateExamSectionResource extends JsonResource
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
            "title" => $this->title,
            "slug"=>$this->slug,
            "detail" => $this->detail,
            "total_questions" => $this->whenCounted('questions'),
            "is_published" => (bool)$this->is_published,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
        ];
    }
}
