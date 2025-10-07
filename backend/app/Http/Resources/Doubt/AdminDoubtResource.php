<?php

namespace App\Http\Resources\Doubt;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDoubtResource extends JsonResource
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
            'status' => $this->status,
            'doubt' => $this->doubt,
            'date' => $this->date,
            'remark' => $this->remark,
            'question' => [
                'question' => $this->question->question ?? null,
                'options' => $this->question->options ?? null,
                'explanation' => $this->question->explanation ?? null,
            ],
            'student' => [
                'name' => $this->student->name ?? null,
            ],
        ];
    }
}
