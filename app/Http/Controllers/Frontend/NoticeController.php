<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Resources\Notice\NoticeDetailResource;
use App\Http\Resources\Notice\NoticeListResource;
use App\Models\Notice;
use App\Models\NoticeReport;
use App\Models\NoticeSubscription;
use App\Services\Notices\Enrichment\NoticeTagVocabulary;
use App\Services\Notices\NoticePublisher;
use App\Traits\PaginatorTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

/**
 * Public notices API - used by the website (/notices) and the mobile apps.
 */
class NoticeController extends Controller
{
    use PaginatorTrait;

    private const CACHE_MINUTES = 10;

    /**
     * GET free/notices
     *   category, sub_category, type, province, org, q,
     *   closing (e.g. 7d = deadline within 7 days), upcoming (exam within Nd),
     *   archived=1 (include archived), page, per_page (max 50)
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(Notice::CATEGORIES)],
            'sub_category' => ['nullable', 'string', 'max:64'],
            'type' => ['nullable', Rule::in(Notice::TYPES)],
            'province' => ['nullable', Rule::in(config('notices.provinces'))],
            'org' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:100'],
            'closing' => ['nullable', 'regex:/^\d{1,3}d$/'],
            'upcoming' => ['nullable', 'regex:/^\d{1,3}d$/'],
            'archived' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $key = NoticePublisher::cacheKey('list:'.md5(json_encode($filters)));
        $data = Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), function () use ($filters) {
            $query = $this->filtered($filters);

            if (isset($filters['closing'])) {
                $query->orderBy('application_deadline_ad');
            } elseif (isset($filters['upcoming'])) {
                $query->orderBy('exam_date_ad');
            } else {
                $query->orderByDesc('is_featured')->latestFirst();
            }

            $page = $query->paginate($filters['per_page'] ?? 20);

            return $this->setupPagination($page, fn ($items) => NoticeListResource::collection($items)->resolve())->data;
        });

        return Response::apiSuccess('Notices list', $data);
    }

    /**
     * GET free/notices/home - landing sections + per-category counts.
     */
    public function home(Request $request)
    {
        $category = $request->validate(['category' => ['nullable', Rule::in(Notice::CATEGORIES)]])['category'] ?? null;

        $data = Cache::remember(NoticePublisher::cacheKey('home:'.($category ?? 'all')), now()->addMinutes(self::CACHE_MINUTES), function () use ($category) {
            $base = fn () => Notice::published()->when($category, fn ($q) => $q->where('category', $category));
            $list = fn (Builder $q, int $n) => NoticeListResource::collection($q->limit($n)->get())->resolve();

            return [
                'closing_soon' => $list($base()->closingWithin(7)->orderBy('application_deadline_ad'), 8),
                'upcoming_exams' => $list($base()->examWithin(30)->orderBy('exam_date_ad'), 8),
                'latest' => $list($base()->orderByDesc('is_featured')->latestFirst(), 20),
                'counts' => Notice::published()->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category'),
            ];
        });

        return Response::apiSuccess('Notices home', $data);
    }

    /** GET free/notices/meta - filter options. */
    public function meta()
    {
        $data = Cache::remember(NoticePublisher::cacheKey('meta'), now()->addHour(), fn () => [
            'categories' => Notice::CATEGORIES,
            // Only options that match at least one published notice, so no
            // filter choice leads to a guaranteed-empty page.
            'types' => array_values(array_intersect(Notice::TYPES, Notice::published()->where('notice_type', '!=', 'other')->distinct()->pluck('notice_type')->all())),
            'provinces' => array_values(array_intersect(config('notices.provinces'), Notice::published()->whereNotNull('province')->distinct()->pluck('province')->all())),
            'has_deadlines' => Notice::published()->whereNotNull('application_deadline_ad')->exists(),
            'sub_categories' => config('notices.sub_categories'),
            'organizations' => Notice::published()->select('organization', 'category')->distinct()->orderBy('organization')->get()
                ->groupBy('category')->map(fn ($rows) => $rows->pluck('organization')->values()),
            'exam_tags' => app(NoticeTagVocabulary::class)->all(),
        ]);

        return Response::apiSuccess('Notice filters', $data);
    }

    /** GET free/notices/{slug} */
    public function show(string $slug)
    {
        $notice = Notice::visible()->where('slug', $slug)->first();
        if (! $notice) {
            return Response::apiError('Notice not found.', null, 404);
        }

        Notice::whereKey($notice->id)->increment('view_count');

        $data = Cache::remember(NoticePublisher::cacheKey("show:{$notice->id}"), now()->addMinutes(self::CACHE_MINUTES), function () use ($notice) {
            $related = Notice::published()
                ->where('id', '!=', $notice->id)
                ->where(function ($q) use ($notice) {
                    $q->where('organization', $notice->organization);
                    foreach (array_slice($notice->exam_tags ?? [], 0, 3) as $tag) {
                        $q->orWhereJsonContains('exam_tags', $tag);
                    }
                })
                ->latestFirst()
                ->limit(6)
                ->get();

            return (new NoticeDetailResource($notice))->resolve() + [
                'related' => NoticeListResource::collection($related)->resolve(),
            ];
        });

        return Response::apiSuccess('Notice details', $data);
    }

    /** POST free/notices/{slug}/report - "Report an error". */
    public function report(Request $request, string $slug)
    {
        $notice = Notice::visible()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'field' => ['nullable', 'string', 'max:64'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        NoticeReport::create($data + ['notice_id' => $notice->id, 'ip' => $request->ip()]);

        return Response::apiSuccess('Thank you - our team will review this notice.', null, 201);
    }

    /** POST free/notices/subscribe - email alerts (daily digest). */
    public function subscribe(Request $request)
    {
        $data = $this->validateSubscription($request, ['email' => ['required', 'email', 'max:255']]);

        NoticeSubscription::updateOrCreate(
            ['email' => strtolower($data['email']), 'channel' => 'email'],
            ['categories' => $data['categories'] ?? [], 'exam_tags' => $data['exam_tags'] ?? [], 'is_active' => true],
        );

        return Response::apiSuccess('You will receive a daily digest of matching notices.', null, 201);
    }

    /** POST notices/subscribe-push (student JWT) - app push alerts. */
    public function subscribePush(Request $request)
    {
        $data = $this->validateSubscription($request);
        $student = auth('api')->user();

        $subscription = NoticeSubscription::updateOrCreate(
            ['student_profile_id' => $student->id, 'channel' => 'push'],
            ['categories' => $data['categories'] ?? [], 'exam_tags' => $data['exam_tags'] ?? [], 'is_active' => $request->boolean('is_active', true)],
        );

        return Response::apiSuccess('Notice alerts updated.', $subscription->only(['categories', 'exam_tags', 'is_active']));
    }

    /** GET free/notices/unsubscribe/{token} */
    public function unsubscribe(string $token)
    {
        $updated = NoticeSubscription::where('unsubscribe_token', $token)->update(['is_active' => false]);

        return $updated
            ? response('<p style="font-family:sans-serif">You have been unsubscribed from ExamsNepal notice alerts.</p>')
            : response('<p style="font-family:sans-serif">This link is invalid or already used.</p>', 404);
    }

    /** GET free/notices/sitemap - slugs + lastmod for the XML sitemap and RSS. */
    public function sitemap(Request $request)
    {
        $limit = min((int) $request->query('limit', 5000), 50000);
        $data = Notice::visible()->latest('published_at')->limit($limit)
            ->get(['slug', 'title_en', 'title_original', 'summary_en', 'organization', 'category', 'published_at', 'updated_at'])
            ->map(fn (Notice $n) => [
                'slug' => $n->slug,
                'title' => $n->displayTitle(),
                'summary' => $n->summary_en,
                'organization' => $n->organization,
                'category' => $n->category,
                'published_at' => $n->published_at?->toIso8601String(),
                'lastmod' => $n->updated_at?->toIso8601String(),
            ]);

        return Response::apiSuccess('Notice sitemap', $data);
    }

    private function filtered(array $f): Builder
    {
        $query = ! empty($f['archived']) ? Notice::visible() : Notice::published();

        return $query
            ->when($f['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($f['sub_category'] ?? null, fn ($q, $v) => $q->where('sub_category', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('notice_type', $v))
            ->when($f['province'] ?? null, fn ($q, $v) => $q->where('province', $v))
            ->when($f['org'] ?? null, fn ($q, $v) => $q->where('organization', $v))
            ->when($f['closing'] ?? null, fn ($q, $v) => $q->closingWithin((int) $v))
            ->when($f['upcoming'] ?? null, fn ($q, $v) => $q->examWithin((int) $v))
            ->when($f['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('title_en', 'like', $like)
                    ->orWhere('title_ne', 'like', $like)
                    ->orWhere('title_original', 'like', $like)
                    ->orWhere('organization', 'like', $like));
            });
    }

    private function validateSubscription(Request $request, array $extra = []): array
    {
        return $request->validate($extra + [
            'categories' => ['nullable', 'array'],
            'categories.*' => [Rule::in(Notice::CATEGORIES)],
            'exam_tags' => ['nullable', 'array', 'max:30'],
            'exam_tags.*' => [Rule::in(array_keys(app(NoticeTagVocabulary::class)->all()))],
        ]);
    }
}
