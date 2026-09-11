<?php

namespace App\Http\Resources\Corporate\Classroom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassDiscussionPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'author' => new ClassDiscussionAuthorResource($this->whenLoaded('author')),
            'replies' => ClassDiscussionReplyResource::collection($this->whenLoaded('replies')),
            'replies_count' => $this->whenCounted('replies'),
            'created_at' => $this->created_at,
        ];
    }
}
