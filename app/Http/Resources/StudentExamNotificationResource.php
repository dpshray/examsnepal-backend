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
        // $item = json_decode($this->data);
        $notification = $this->studentNotification;
        return [
            'notification_id' => $this->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'notified_at' => $notification->created_at,
        ];
    }
}
