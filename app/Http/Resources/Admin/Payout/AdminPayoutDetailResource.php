<?php

namespace App\Http\Resources\Admin\Payout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPayoutDetailResource extends JsonResource
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
            'revenue_amount' => (float) $this->revenue_amount,
            'pool_percentage_used' => (float) $this->pool_percentage_used,
            'pool_amount' => (float) $this->pool_amount,
            'teacher_unique_completions' => $this->teacher_unique_completions,
            'total_unique_completions' => $this->total_unique_completions,
            'share_percentage' => (float) $this->share_percentage,
            'payout_amount' => (float) $this->payout_amount,
            'status' => $this->status,
            'remark' => $this->remark,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->fullname ?? $this->approver->username,
            ] : null),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'exams' => $this->whenLoaded('lineItems', fn () => $this->lineItems->map(fn ($item) => [
                'exam_id' => $item->exam_id,
                'exam_name' => $item->exam?->exam_name,
                'unique_completions' => $item->unique_completions,
                'contribution_percentage' => (float) $item->contribution_percentage,
            ])),
        ];
    }
}
