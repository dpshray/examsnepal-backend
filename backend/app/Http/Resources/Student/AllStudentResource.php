<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllStudentResource extends JsonResource
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
            'name'                => $this->name,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'exam_type'           => optional($this->examType)->name, // if you have examType relationship
            'registered_date'     => $this->created_at->format('Y-m-d'),
            'is_subscripted'      => $this->is_subscripted, // uses accessor

            // Subscription details from subscribed() relation
            'subscription_start_date' => optional($this->subscribed)->start_date,
            'subscription_end_date'   => optional($this->subscribed)->end_date,
        ];
    }
}
