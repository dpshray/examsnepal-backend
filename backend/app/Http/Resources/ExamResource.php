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
        $is_student_exam_loaded = $this->whenLoaded('student_exams');
        $user_id = Auth::guard('api')->id();
        $data = [
            "id" => $this->id,
            "exam_name" => $this->exam_name,
            "status" => $this->status,
            "user_id" => $this->user_id,
            "questions_count" => $this->whenCounted('questions'),
            "user" => $this->whenLoaded('user')
        ];
        $data['players'] = $this->student_exams->where('completed',1)->map(fn($item) => [
            'id' => $item->student->id, 
            'name' => $item->student->name,
            'solutions' => [
                'corrected' => optional($item->answers)->where('is_correct',1)->count(),
                'total' => $item->answers->count()
            ]
        ])
        ->sortByDesc(fn($player) => $player['solutions']['corrected'])
        ->values();
        return $data;
    }
}
