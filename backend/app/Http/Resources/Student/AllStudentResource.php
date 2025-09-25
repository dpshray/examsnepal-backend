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
            'id'=>$this->id,
            'name'=> $this->name,
            'email'=> $this->email,
            'phone'=> $this->phone,
            'exam_type'=> $this->exam_type_id,
            'registered_date'=> $this->date,
            'is_subscripted'=> $this->subscribed ? 1 : 0,

            // Subscription details from subscribed() relation
            'subscription_start_date'=> optional($this->subscribed)->start_date,
            'subscription_end_date'=> optional($this->subscribed)->end_date,
        ];
    }
}
