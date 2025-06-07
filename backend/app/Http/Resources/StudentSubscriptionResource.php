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
            "student_profile_id" => $this->student_profile_id,
            "starts_at" => $this->starts_at,
            "ends_at" => $this->ends_at,
            "subscribed_at" => $this->subscribed_at,
            "subscription" => $this->whenLoaded('subscriptionType')
        ];
    }
}
