<?php

namespace App\Http\Resources\Admin\Notification;

use App\Enums\ExamTypeEnum;
use App\Enums\NotificationTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminNotificationListResource extends JsonResource
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
            "notification_id" => $this->id,
            "title" => $this->title,
            "body" => $this->body,
            "type" => $this->getType($this),
            "exam" => $this->exam ? [
                'exam_name' => $this->exam->exam_name,
                'exam_type_id' => $this->exam->exam_type_id,
                'exam_category' => ExamTypeEnum::getKeyByValue($this->exam->status)
            ]: null,
            "notified_at" => $this->created_at->format('Y/m/d H:i:s'),
        ];
    }

    private function getType($notification){
        if ($notification->title == 'Doubt Resolved') {
            return NotificationTypeEnum::DOUBT_RESOLVED->value;
        }
        elseif (in_array($notification->type, ['mock test', 'free quiz', 'sprint quiz'])) {
            return NotificationTypeEnum::NEW_EXAM->value;
        }
        elseif ($notification->type == 'Notification') {
            return NotificationTypeEnum::BULK_NOTIFICATION->value;
        }
        elseif(in_array($notification->type, [NotificationTypeEnum::DOUBT_RESOLVED->value,NotificationTypeEnum::NEW_EXAM->value,NotificationTypeEnum::BULK_NOTIFICATION->value])) {
            return $notification->type;
        }else{
            return 'N/A';
        }
    }
}
