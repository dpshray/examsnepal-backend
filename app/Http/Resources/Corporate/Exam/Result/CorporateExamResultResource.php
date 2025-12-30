<?php

namespace App\Http\Resources\Corporate\Exam\Result;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorporateExamResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        $collection = collect($this->collection);
        return $this->collection->map(function($result) {
            return [
                'rank' => $result['rank'],
                'participant_id' => $result['participant_id'],
                'name' => $result['name'],
                'email' => $result['email'],
                'phone' => $result['phone'],
                'section_wise_marks' => $result['section_wise_marks'],
                'total_marks' => $result['total_marks'],
            ];
        });
    }
}
