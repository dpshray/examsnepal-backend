<?php

namespace App\Http\Resources\Notice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Card-sized notice payload for lists (web + mobile app). */
class NoticeListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title_en' => $this->title_en,
            'title_ne' => $this->title_ne,
            'title_original' => $this->title_original,
            'category' => $this->category,
            'sub_category' => $this->sub_category,
            'notice_type' => $this->notice_type,
            'organization' => $this->organization,
            'province' => $this->province,
            'published_date_bs' => $this->published_date_bs,
            'published_date_ad' => $this->published_date_ad?->toDateString(),
            'application_deadline_ad' => $this->application_deadline_ad?->toDateString(),
            'exam_date_ad' => $this->exam_date_ad?->toDateString(),
            'exam_date_bs' => $this->exam_date_bs,
            'is_featured' => $this->is_featured,
            'is_archived' => $this->status === 'archived',
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
