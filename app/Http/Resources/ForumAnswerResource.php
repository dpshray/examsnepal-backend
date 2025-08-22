<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForumAnswerResource extends JsonResource
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
            "id" => $this->id,
            "forum_question_id" => $this->forum_question_id,
            "user_id" => $this->user_id,
            "answer" => $this->answer,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            'student_profile' => $this->whenLoaded('studentProfile', fn() => new StudentResource($this->studentProfile))
        ];

    }
}
