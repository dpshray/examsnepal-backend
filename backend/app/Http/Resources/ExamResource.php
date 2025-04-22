<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ExamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        $players = $this->whenLoaded('players');
        $user_id = Auth::guard('api')->id();
        return [
            "id" => $this->id,
            "exam_name" => $this->exam_name,
            "status" => $this->status,
            "user_id" => $this->user_id,
            "questions_count" => $this->whenCounted('questions'),
            "user" => $this->whenLoaded('user'),
            'is_completed' => $this->whenLoaded('players', function() use($user_id){
                return ($this->players->where('student_id', $user_id)->isNotEmpty() && $this->players->where('student_id', $user_id)->first()->completed);
            })
        ];
    }
}
