<?php

namespace App\Http\Controllers\Admin\Notice;

use App\Http\Controllers\Controller;
use App\Jobs\FetchNoticeSourceJob;
use App\Models\NoticeFetchLog;
use App\Models\NoticeSource;
use App\Services\Notices\NoticeFetcher;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Throwable;

class AdminNoticeSourceController extends Controller
{
    use PaginatorTrait;

    public function index(Request $request)
    {
        $sources = NoticeSource::query()
            ->withCount(['notices', 'notices as pending_count' => fn ($q) => $q->where('status', 'pending')])
            ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->query('fetch_type'), fn ($q, $v) => $q->where('fetch_type', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%{$v}%")->orWhere('organization', 'like', "%{$v}%")))
            ->orderByDesc('is_active')->orderByDesc('priority')->orderBy('name')
            ->get()
            ->map(fn (NoticeSource $s) => $s->toArray() + ['is_healthy' => $s->isHealthy()]);

        return Response::apiSuccess('Notice sources', $sources);
    }

    public function show(NoticeSource $noticeSource)
    {
        return Response::apiSuccess('Notice source', $noticeSource->toArray() + [
            'is_healthy' => $noticeSource->isHealthy(),
            'recent_logs' => $noticeSource->fetchLogs()->latest('started_at')->limit(20)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $source = NoticeSource::create($this->validated($request));

        return Response::apiSuccess('Source created', $source, 201);
    }

    public function update(Request $request, NoticeSource $noticeSource)
    {
        $noticeSource->update($this->validated($request, $noticeSource));

        return Response::apiSuccess('Source updated', $noticeSource->fresh());
    }

    public function destroy(NoticeSource $noticeSource)
    {
        $noticeSource->delete();

        return Response::apiSuccess('Source deleted');
    }

    /**
     * POST admin/notice-sources/{id}/test-fetch - runs the adapter with the
     * posted (unsaved) config, or the saved one, and returns parsed items
     * without saving anything.
     */
    public function testFetch(Request $request, NoticeFetcher $fetcher, ?NoticeSource $noticeSource = null)
    {
        $source = $noticeSource ?? new NoticeSource();
        if ($request->hasAny(['list_url', 'fetch_type', 'selectors'])) {
            $source = ($noticeSource ? $noticeSource->replicate() : new NoticeSource())->fill($this->validated($request, $noticeSource, partial: true));
            $source->id = $noticeSource?->id ?? 0;
        }

        try {
            $items = $fetcher->preview($source);
        } catch (Throwable $e) {
            return Response::apiError(class_basename($e).': '.$e->getMessage(), null, 422);
        }

        return Response::apiSuccess(count($items).' item(s) parsed', $items);
    }

    /** POST admin/notice-sources/{id}/fetch-now - queue a real fetch. */
    public function fetchNow(NoticeSource $noticeSource)
    {
        if ($noticeSource->fetch_type === 'manual') {
            return Response::apiError('Manual sources are not fetched.', null, 422);
        }
        FetchNoticeSourceJob::dispatch($noticeSource->id)->onQueue(config('notices.queue'));

        return Response::apiSuccess('Fetch queued');
    }

    /** GET admin/notice-fetch-logs?source_id=&status= */
    public function logs(Request $request)
    {
        $logs = NoticeFetchLog::with('source:id,name')
            ->when($request->query('source_id'), fn ($q, $v) => $q->where('source_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest('started_at')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return Response::apiSuccess('Fetch logs', $this->setupPagination($logs)->data);
    }

    private function validated(Request $request, ?NoticeSource $source = null, bool $partial = false): array
    {
        $required = ($source || $partial) ? 'sometimes' : 'required';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'organization' => [$required, 'string', 'max:255'],
            'category' => [$required, Rule::in(['loksewa', 'entrance', 'license'])],
            'sub_category' => [$required, 'string', 'max:64'],
            'province' => ['nullable', Rule::in(config('notices.provinces'))],
            'base_url' => [$required, 'url', 'max:255'],
            'list_url' => [$required, 'url', 'max:1024'],
            'fetch_type' => [$required, Rule::in(['html_list', 'rss', 'json_api', 'pdf_list', 'manual'])],
            'adapter_class' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_]+$/'],
            'selectors' => ['nullable', 'array'],
            'language' => ['nullable', Rule::in(['ne', 'en', 'mixed'])],
            'fetch_interval_minutes' => ['nullable', 'integer', 'min:30', 'max:10080'],
            'is_active' => ['nullable', 'boolean'],
            'is_trusted' => ['nullable', 'boolean'],
            'verify_ssl' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Only official sites: refuse obvious aggregator/news domains.
        foreach (['base_url', 'list_url'] as $key) {
            if (isset($data[$key]) && ! preg_match('#^https?://[^/]*\.(gov\.np|edu\.np|org\.np|mil\.np|com\.np|net\.np|edu)(:\d+)?(/|$)#i', $data[$key])) {
                abort(422, "{$key} must be an official Nepali (.np) or institutional domain.");
            }
        }

        return $data;
    }
}
