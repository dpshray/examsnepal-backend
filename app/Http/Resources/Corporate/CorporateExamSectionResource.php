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
            "detail" => $this->detail,
            "total_questions" => 100,
            "is_published" => (bool)$this->is_published,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
        ];
    }
}
