<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherPayoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_type' => $this->whenLoaded('examType', fn () => [
                'id' => $this->examType->id,
                'name' => $this->examType->name,
            ]),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'teacher_unique_completions' => $this->teacher_unique_completions,
            'total_unique_completions' => $this->total_unique_completions,
            'share_percentage' => (float) $this->share_percentage,
            'payout_amount' => (float) $this->payout_amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toDateTimeString(),
        ];
    }
}
