<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
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
            'exam_id' => $this->exam_id,
            'question' => $this->question,
            'explanation' => $this->explanation,
            'options' => $this->whenLoaded('options')
        ];
    }
}
