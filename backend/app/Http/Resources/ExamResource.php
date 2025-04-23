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
        $is_players_loaded = $this->whenLoaded('student_exams');
        $user_id = Auth::guard('api')->id();
        $data = [
            "id" => $this->id,
            "exam_name" => $this->exam_name,
            "status" => $this->status,
            "user_id" => $this->user_id,
            "questions_count" => $this->whenCounted('questions'),
            "user" => $this->whenLoaded('user'),
            'is_completed' => $this->whenLoaded('student_exams', function() use($user_id){
                return ($this->student_exams->where('student_id', $user_id)->isNotEmpty() && $this->student_exams->where('student_id', $user_id)->first()->completed);
            })
        ];
        if ($is_players_loaded && str_contains($request->url(), '/completed')) {
            $data['student_exams'] = $this->student_exams->map(fn($item) => ['id' => $item->student->id, 'name' => $item->student->name]);
        }
        return $data;
    }
}
