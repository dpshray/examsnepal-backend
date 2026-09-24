<?php

namespace App\Http\Controllers\Admin\Notice;

use App\Http\Controllers\Controller;
use App\Jobs\EnrichNoticeJob;
use App\Models\Notice;
use App\Services\Notices\Enrichment\NoticeTagVocabulary;
use App\Services\Notices\NoticePublisher;
use App\Services\Notices\Support\NepaliDate;
use App\Services\Notices\Support\TitleNormalizer;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminNoticeController extends Controller
{
    use PaginatorTrait;

    public function __construct(private readonly NoticePublisher $publisher) {}

    /** GET admin/notices?status=pending&category=&source_id=&q=&per_page= */
    public function index(Request $request)
    {
        $notices = Notice::query()
            ->with('source:id,name')
            ->withCount(['reports as open_reports_count' => fn ($q) => $q->where('is_resolved', false)])
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->query('source_id'), fn ($q, $v) => $q->where('source_id', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('notice_type', $v))
            ->when($request->boolean('has_reports'), fn ($q) => $q->whereHas('reports', fn ($r) => $r->where('is_resolved', false)))
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->query('q'), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('title_original', 'like', $like)->orWhere('title_en', 'like', $like)->orWhere('organization', 'like', $like));
            })
            // Review queue: oldest pending first so nothing starves.
            ->when($request->query('status') === 'pending', fn ($q) => $q->orderBy('created_at'), fn ($q) => $q->latest('id'))
            ->paginate(min((int) $request->query('per_page', 20), 100));

        return Response::apiSuccess('Notices', $this->setupPagination($notices)->data);
    }

    /** GET admin/notices/stats - badge counts. */
    public function stats()
    {
        return Response::apiSuccess('Notice stats', [
            'by_status' => Notice::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'open_reports' => \App\Models\NoticeReport::where('is_resolved', false)->count(),
            'enrichment_failures' => Notice::where('status', 'pending')->whereNotNull('enrichment_error')->count(),
            'tokens_last_30_days' => Notice::where('enriched_at', '>=', now()->subDays(30))
                ->selectRaw('coalesce(sum(ai_input_tokens),0) as input, coalesce(sum(ai_output_tokens),0) as output')->first(),
        ]);
    }

    /** GET admin/notices/meta - enums + tag vocabulary for the forms. */
    public function meta(NoticeTagVocabulary $tags)
    {
        return Response::apiSuccess('Notice form options', [
            'categories' => Notice::CATEGORIES,
            'types' => Notice::TYPES,
            'statuses' => Notice::STATUSES,
            'provinces' => config('notices.provinces'),
            'sub_categories' => config('notices.sub_categories'),
            'exam_tags' => $tags->all(),
            'fetch_types' => ['html_list', 'rss', 'json_api', 'pdf_list', 'manual'],
        ]);
    }

    public function show(Notice $notice)
    {
        $notice->load(['source:id,name,list_url,is_trusted', 'reports' => fn ($q) => $q->latest()]);

        return Response::apiSuccess('Notice', $notice->makeVisible('ai_raw'));
    }

    /** POST admin/notices - manual entry (manual sources, urgent notices). */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['content_hash'] = TitleNormalizer::contentHash((int) ($data['source_id'] ?? 0), $data['title_original'], $data['source_url']);

        if (Notice::where('content_hash', $data['content_hash'])->exists()) {
            return Response::apiError('This notice already exists.', null, 422);
        }

        $notice = Notice::create($data + ['status' => $data['status'] ?? 'pending']);
        $notice->refreshSlugFromEnglishTitle();
        $notice->save();

        if ($notice->status === 'published') {
            $this->publisher->publish($notice);
        }

        return Response::apiSuccess('Notice created', $notice->fresh(), 201);
    }

    public function update(Request $request, Notice $notice)
    {
        $data = $this->validated($request, $notice);
        $notice->fill($data);
        $notice->refreshSlugFromEnglishTitle();

        if ($notice->isDirty('status') && $notice->status === 'published') {
            $this->publisher->publish($notice);
        } else {
            $notice->save();
            $this->publisher->flushCaches();
        }

        return Response::apiSuccess('Notice updated', $notice->fresh());
    }

    public function destroy(Notice $notice)
    {
        $notice->delete();
        $this->publisher->flushCaches();

        return Response::apiSuccess('Notice deleted');
    }

    public function approve(Notice $notice)
    {
        $this->publisher->publish($notice);

        return Response::apiSuccess('Notice published', $notice->fresh());
    }

    public function reject(Notice $notice)
    {
        $notice->update(['status' => 'rejected']);
        $this->publisher->flushCaches();

        return Response::apiSuccess('Notice rejected', $notice->fresh());
    }

    public function toggleFeature(Notice $notice)
    {
        $notice->update(['is_featured' => ! $notice->is_featured]);
        $this->publisher->flushCaches();

        return Response::apiSuccess($notice->is_featured ? 'Notice featured' : 'Notice unfeatured', $notice->fresh());
    }

    /** Re-run AI extraction (e.g. after the PDF was replaced). */
    public function reEnrich(Notice $notice)
    {
        if (! \App\Services\Notices\Enrichment\NoticeAiClient::isEnabled() || ! \App\Services\Notices\Enrichment\NoticeAiClient::isConfigured()) {
            return Response::apiError('No AI provider key is configured (OPENROUTER_API_KEY / ANTHROPIC_API_KEY).', null, 422);
        }
        EnrichNoticeJob::dispatch($notice->id, force: true)->onQueue(config('notices.queue'));

        return Response::apiSuccess('Re-extraction queued');
    }

    private function validated(Request $request, ?Notice $notice = null): array
    {
        $required = $notice ? 'sometimes' : 'required';
        $data = $request->validate([
            'source_id' => ['nullable', 'exists:notice_sources,id'],
            'category' => [$required, Rule::in(Notice::CATEGORIES)],
            'sub_category' => ['nullable', 'string', 'max:64'],
            'organization' => [$required, 'string', 'max:255'],
            'province' => ['nullable', Rule::in(config('notices.provinces'))],
            'title_original' => [$required, 'string', 'max:2000'],
            'title_en' => ['nullable', 'string', 'max:500'],
            'title_ne' => ['nullable', 'string', 'max:500'],
            'summary_en' => ['nullable', 'string', 'max:2000'],
            'summary_ne' => ['nullable', 'string', 'max:2000'],
            'source_url' => [$required, 'url', 'max:1024'],
            'attachment_urls' => ['nullable', 'array'],
            'attachment_urls.*.url' => ['required', 'url'],
            'attachment_urls.*.name' => ['nullable', 'string', 'max:255'],
            'published_date_bs' => ['nullable', 'string', 'max:20'],
            'published_date_ad' => ['nullable', 'date'],
            'application_start_ad' => ['nullable', 'date'],
            'application_deadline_ad' => ['nullable', 'date'],
            'double_fee_deadline_ad' => ['nullable', 'date'],
            'exam_date_ad' => ['nullable', 'date'],
            'exam_date_bs' => ['nullable', 'string', 'max:20'],
            'notice_type' => ['nullable', Rule::in(Notice::TYPES)],
            'posts' => ['nullable', 'array'],
            'posts.*.name' => ['required', 'string', 'max:255'],
            'posts.*.service_group' => ['nullable', 'string', 'max:255'],
            'posts.*.level' => ['nullable', 'string', 'max:100'],
            'posts.*.seats' => ['nullable', 'integer', 'min:0'],
            'posts.*.qualification' => ['nullable', 'string', 'max:500'],
            'fees' => ['nullable', 'array'],
            'eligibility' => ['nullable', 'array'],
            'exam_centers' => ['nullable', 'array'],
            'exam_tags' => ['nullable', 'array'],
            'exam_tags.*' => ['string', 'max:64'],
            'status' => ['nullable', Rule::in(Notice::STATUSES)],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        // Admins type dates in either calendar; keep BS and AD in sync.
        if (! empty($data['published_date_bs']) && empty($data['published_date_ad'])) {
            $data['published_date_bs'] = NepaliDate::normalizeBs($data['published_date_bs']);
            $data['published_date_ad'] = NepaliDate::bsToAd($data['published_date_bs'])?->toDateString();
        } elseif (! empty($data['published_date_ad']) && empty($data['published_date_bs'])) {
            $data['published_date_bs'] = NepaliDate::adToBs($data['published_date_ad']);
        }
        if (! empty($data['exam_date_bs']) && empty($data['exam_date_ad'])) {
            $data['exam_date_bs'] = NepaliDate::normalizeBs($data['exam_date_bs']);
            $data['exam_date_ad'] = NepaliDate::bsToAd($data['exam_date_bs'])?->toDateString();
        } elseif (! empty($data['exam_date_ad']) && empty($data['exam_date_bs'])) {
            $data['exam_date_bs'] = NepaliDate::adToBs($data['exam_date_ad']);
        }
        if (isset($data['exam_tags'])) {
            $data['exam_tags'] = app(NoticeTagVocabulary::class)->filter($data['exam_tags']);
        }
        if (isset($data['title_original'])) {
            $data['title_original'] = Str::squish($data['title_original']);
        }

        return $data;
    }
}
