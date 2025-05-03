<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForumQuestionResource extends JsonResource
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
        "deleted" => $this->deleted,
        "user_id" => $this->user_id,
        "question" => $this->question,
        "created_at" => $this->created_at,
        "updated_at" => $this->updated_at,
        "answers_count" => $this->answers_count,
        "student_profile" => $this->whenLoaded('studentProfile', fn() => new StudentResource($this->studentProfile)),
        "answers" => $this->whenLoaded('answers', fn() => new ForumAnswerCollection($this->answers))
        ];
    }
}
