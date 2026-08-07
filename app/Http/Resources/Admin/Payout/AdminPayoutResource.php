<?php

namespace App\Http\Resources\Admin\Payout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPayoutResource extends JsonResource
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
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->fullname ?? $this->teacher->username,
                'email' => $this->teacher->email,
            ]),
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
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'paid_at' => $this->paid_at?->toDateTimeString(),
        ];
    }
}
