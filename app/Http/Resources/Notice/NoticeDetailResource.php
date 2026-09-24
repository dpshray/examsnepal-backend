<?php

namespace App\Http\Resources\Notice;

use App\Services\Notices\Enrichment\NoticeTagVocabulary;
use App\Services\Notices\Support\NepaliDate;
use Illuminate\Http\Request;

class NoticeDetailResource extends NoticeListResource
{
    public function toArray(Request $request): array
    {
        $bs = fn ($date) => $date ? NepaliDate::adToBs($date) : null;

        return parent::toArray($request) + [
            'summary_en' => $this->summary_en,
            'summary_ne' => $this->summary_ne,
            'source_url' => $this->source_url,
            'attachment_urls' => $this->attachment_urls ?? [],
            'application_start_ad' => $this->application_start_ad?->toDateString(),
            'application_start_bs' => $bs($this->application_start_ad),
            'application_deadline_bs' => $bs($this->application_deadline_ad),
            'double_fee_deadline_ad' => $this->double_fee_deadline_ad?->toDateString(),
            'double_fee_deadline_bs' => $bs($this->double_fee_deadline_ad),
            'posts' => $this->posts ?? [],
            'fees' => $this->fees ?? [],
            'eligibility' => $this->eligibility ?? [],
            'exam_centers' => $this->exam_centers ?? [],
            'exam_tags' => $this->exam_tags ?? [],
            'exam_links' => app(NoticeTagVocabulary::class)->links($this->exam_tags ?? []),
            'is_ai_summary' => (bool) $this->enriched_at,
        ];
    }
}
