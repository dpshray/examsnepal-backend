<?php

namespace App\Http\Resources\Corporate\Classroom;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Renders whichever author a post/reply has (teacher App\Models\User or
 * student App\Models\InstituteStudent) into a single consistent shape -
 * the two models don't share a display-name column.
 */
class ClassDiscussionAuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isTeacher = $this->resource instanceof User;

        return [
            'id' => $this->id,
            'name' => $isTeacher ? ($this->fullname ?: $this->username ?: 'Teacher') : ($this->name ?: 'Student'),
            'role' => $isTeacher ? 'teacher' : 'student',
        ];
    }
}
