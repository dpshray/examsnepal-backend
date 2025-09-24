<?php

namespace App\Http\Resources\ForumQuestion;

use App\Http\Resources\ForumAnswerCollection;
use App\Http\Resources\StudentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReplyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return[
            "student_profile" => $this->whenLoaded('studentProfile', fn() => new StudentResource($this->studentProfile)),
            "answers" => $this->whenLoaded('answers', fn() => new ForumAnswerCollection($this->answers))
        ];
    }
}
