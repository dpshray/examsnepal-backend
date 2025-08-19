<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentSubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "price" => $this->price,
            "paid" => $this->paid,
            "student_profile_id" => (int)$this->student_profile_id,
            "starts_at" => $this->starts_at,
            "ends_at" => $this->ends_at,
            "subscribed_at" => $this->subscribed_at,
            "subscription" => $this->whenLoaded('subscriptionType', function(){
                $subscription_type = $this->subscriptionType;
                if ($this->subscriptionType) {
                    return [
                        "id"  => (int)$subscription_type->id,
                        "exam_type_id"  => (int)$subscription_type->exam_type_id,
                        "duration"  => $subscription_type->duration,
                        "price"  => $subscription_type->price,
                        "status"  => (int)$subscription_type->status,
                    ];
                }else{
                    return null;
                }
            })
        ];
    }
}
