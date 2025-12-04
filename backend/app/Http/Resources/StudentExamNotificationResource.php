<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentExamNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        $item = json_decode($this->data);
        return [
            'title' => $item->title,
            'body' => $item->body,
            'notified_at' => $this->notified_at
        ];
    }
}
