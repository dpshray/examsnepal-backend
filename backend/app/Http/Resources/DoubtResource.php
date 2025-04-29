<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoubtResource extends JsonResource
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
            "id" =>  $this->id,
            "solved_by" =>  $this->whenLoaded('solver', $this->solver),
            "doubt" =>  $this->doubt,
            "date" =>  $this->created_at,
            "status" =>  $this->status,
            "remark" =>  $this->remarks,
            "question" => $this->whenLoaded('question', new QuestionResource($this->question))
        ];
    }
}
