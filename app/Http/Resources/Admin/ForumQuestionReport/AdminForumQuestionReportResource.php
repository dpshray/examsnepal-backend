<?php

namespace App\Http\Resources\Admin\ForumQuestionReport;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminForumQuestionReportResource extends JsonResource
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
            'id' => $this->id,
            'question' => $this->question,
            'answer' => $this->answer,
            'user' => $this->student,
            'reason' => $this->reason,
            'report_type' => $this->report_type,
        ];
    }
}
