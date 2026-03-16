<?php

namespace App\Http\Resources\Admin\Subscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminsubscriptionlistResource extends JsonResource
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
            'exam_type_id' => $this->exam_type_id,
            'duration' => $this->duration,
            'price' => $this->price,
            'status' => $this->status,
            'exam_type' => $this->examType->name,
        ];
    }
}
