<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Enums\ExamTypeEnum;
use App\Http\Controllers\Controller;
use App\Services\Marketing\Lifecycle;
use App\Services\Marketing\OverviewReport;
use App\Services\Marketing\PaymentSource;
use App\Services\Marketing\StudentFilter;
use App\Services\Marketing\StudentMetricsCalculator;
use App\Traits\PaginatorTrait;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Marketing dashboard API (docs/marketing.md). Everything student-level reads
 * student_metrics, rebuilt hourly by marketing:refresh-metrics.
 */
class AdminMarketingController extends Controller
{
    use PaginatorTrait;

    private const REPORT_CACHE_SECONDS = 300;

    /** GET admin/marketing/overview?from=Y-m-d&to=Y-m-d&exam_type_id= */
    public function overview(Request $request)
    {
        [$from, $to, $examTypeId] = $this->period($request);
        $key = "marketing:overview:{$from->toDateString()}:{$to->toDateString()}:{$examTypeId}";

        $data = Cache::remember($key, self::REPORT_CACHE_SECONDS, fn () => (new OverviewReport($from, $to, $examTypeId))->build());

        return Response::apiSuccess('Marketing overview', $data + ['metrics_updated_at' => $this->metricsUpdatedAt()]);
    }

    /** GET admin/marketing/cohorts?weeks=8&exam_type_id= */
    public function cohorts(Request $request)
    {
        $weeks = max(2, min(26, (int) $request->query('weeks', 8)));
        $examTypeId = $request->integer('exam_type_id') ?: null;

        $data = Cache::remember("marketing:cohorts:{$weeks}:{$examTypeId}", self::REPORT_CACHE_SECONDS,
            fn () => (new OverviewReport(CarbonImmutable::now(), CarbonImmutable::now(), $examTypeId))->cohorts($weeks));

        return Response::apiSuccess('Weekly retention cohorts', $data);
    }

    /** GET admin/marketing/meta - options for filters. */
    public function meta()
    {
        $examTypes = DB::table('exam_types')->orderBy('name')->get(['id', 'name', 'is_active']);

        return Response::apiSuccess('Marketing filter options', [
            'stages' => Lifecycle::STAGE_PRECEDENCE,
            'segments' => array_merge(
                ['low_performer', 'high_performer', 'score_declining', 'score_improving', 'has_weak_subject',
                    'exam_date_near', 'checkout_abandoned', 'streak_active', 'streak_broken', 'app_user', 'web_only'],
                $examTypes->map(fn ($t) => "exam:{$t->id}")->all(),
            ),
            'exam_types' => $examTypes,
            'subscription_statuses' => ['never', 'active', 'expiring_soon', 'expired'],
            'signup_sources' => DB::table('student_profiles')->whereNotNull('signup_source')->distinct()->orderBy('signup_source')->pluck('signup_source'),
            'tags' => DB::table('student_tags')->distinct()->orderBy('tag')->pluck('tag'),
            'saved_segments' => DB::table('marketing_segments')->orderBy('name')->get(['id', 'name', 'filters'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'filters' => json_decode($s->filters, true)]),
            'metrics_updated_at' => $this->metricsUpdatedAt(),
        ]);
    }

    /** GET admin/marketing/students?{StudentFilter::KEYS}&sort=&dir=&per_page= */
    public function students(Request $request)
    {
        $query = StudentFilter::apply(StudentFilter::base(), $request->query());
        StudentFilter::sort($query, $request->query('sort'), $request->query('dir'));

        $page = $query
            ->leftJoin('exam_types as et', 'et.id', '=', 'm.exam_type_id')
            ->select($this->listColumns())
            ->paginate(min((int) $request->query('per_page', 25), 100));

        $tags = DB::table('student_tags')->whereIn('student_id', collect($page->items())->pluck('student_id'))
            ->get(['student_id', 'tag'])->groupBy('student_id');
        $page->getCollection()->transform(function ($row) use ($tags) {
            $row->segments = json_decode($row->segments ?? '[]', true);
            $row->tags = $tags->get($row->student_id, collect())->pluck('tag')->all();
            return $row;
        });

        return Response::apiSuccess('Students', $this->setupPagination($page)->data);
    }

    /** GET admin/marketing/students/export - CSV of the filtered list. */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->query();
        $columns = [
            'student_id', 'name', 'email', 'phone', 'target_exam', 'signed_up_at', 'lifecycle_stage',
            'total_attempts', 'free_attempts', 'sprint_attempts', 'mock_attempts', 'avg_score_pct',
            'score_trend', 'last_seen_at', 'subscription_status', 'subscription_ends_at', 'lead_score', 'segments',
        ];

        return response()->streamDownload(function () use ($filters, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            StudentFilter::apply(StudentFilter::base(), $filters)
                ->leftJoin('exam_types as et', 'et.id', '=', 'm.exam_type_id')
                ->select($this->listColumns())
                ->orderBy('m.student_id')
                ->chunk(1000, function ($rows) use ($out, $columns) {
                    foreach ($rows as $row) {
                        $row->segments = implode(' ', json_decode($row->segments ?? '[]', true));
                        fputcsv($out, array_map(fn ($c) => $row->{$c}, $columns));
                    }
                });
            fclose($out);
        }, 'students-' . now()->format('Y-m-d-His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    /** GET admin/marketing/students/{id} - profile drawer. */
    public function student(int $id)
    {
        $profile = DB::table('student_profiles as p')
            ->leftJoin('exam_types as et', 'et.id', '=', 'p.exam_type_id')
            ->where('p.id', $id)
            ->first([
                'p.id', 'p.name', 'p.email', 'p.phone', 'p.exam_type_id', 'et.name as target_exam', 'p.created_at',
                'p.target_exam_date', 'p.signup_source', 'p.utm_source', 'p.utm_medium', 'p.utm_campaign',
                'p.requested_from', 'p.last_active_at', 'p.last_platform', 'p.marketing_email_opt_in', 'p.unsubscribed_at',
            ]);
        abort_if(!$profile, 404, 'Student not found');

        $metrics = DB::table('student_metrics')->where('student_id', $id)->first();
        if ($metrics) {
            $metrics->segments = json_decode($metrics->segments ?? '[]', true);
            $metrics->lead_score_breakdown = json_decode($metrics->lead_score_breakdown ?? '{}', true);
        }

        $attempts = DB::table('student_exams as se')
            ->join('exams as e', 'e.id', '=', 'se.exam_id')
            ->where('se.student_id', $id)
            ->orderByRaw('COALESCE(se.submitted_at, se.created_at) IS NULL')
            ->orderByDesc(DB::raw('COALESCE(se.submitted_at, se.created_at)'))
            ->orderByDesc('se.id')
            ->limit(200)
            ->get(['se.id', 'se.exam_id', 'e.exam_name', 'e.status', 'se.is_exam_completed', 'se.score_pct', 'se.submitted_at', 'se.created_at'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'exam_id' => $a->exam_id,
                'exam_name' => $a->exam_name,
                'type' => match ((int) $a->status) {
                    ExamTypeEnum::FREE_QUIZ->value => 'free',
                    ExamTypeEnum::SPRINT_QUIZ->value => 'sprint',
                    ExamTypeEnum::MOCK_TEST->value => 'mock',
                    StudentMetricsCalculator::TOPIC_QUIZ_STATUS => 'topic',
                    default => 'other',
                },
                'completed' => (bool) $a->is_exam_completed,
                'score_pct' => $a->score_pct !== null ? (float) $a->score_pct : null,
                'submitted_at' => $a->submitted_at,
                'started_at' => $a->created_at,
            ]);

        return Response::apiSuccess('Student marketing profile', [
            'profile' => $profile,
            'metrics' => $metrics,
            'tags' => DB::table('student_tags')->where('student_id', $id)->orderBy('tag')->pluck('tag'),
            'attempts' => $attempts,
            'score_series' => $attempts->filter(fn ($a) => $a['completed'] && $a['score_pct'] !== null && $a['submitted_at'])
                ->sortBy('submitted_at')->values()
                ->map(fn ($a) => ['date' => substr($a['submitted_at'], 0, 10), 'score_pct' => $a['score_pct'], 'type' => $a['type'], 'exam_name' => $a['exam_name']]),
            // Per-subject average on single-subject exams (exams.subject_id, see subjects:infer).
            'subjects' => DB::table('student_exams as se')
                ->join('exams as e', 'e.id', '=', 'se.exam_id')
                ->join('subjects as sub', 'sub.id', '=', 'e.subject_id')
                ->where('se.student_id', $id)->where('se.is_exam_completed', 1)->whereNotNull('se.score_pct')
                ->groupBy('sub.id', 'sub.name')
                ->orderBy('avg_score_pct')
                ->get(['sub.id', 'sub.name', DB::raw('COUNT(*) as attempts'), DB::raw('ROUND(AVG(se.score_pct), 1) as avg_score_pct')]),
            'payments' => DB::table('subscribers as s')
                ->leftJoin('subscription_types as st', 'st.id', '=', 's.subscription_type_id')
                ->where('s.student_profile_id', $id)
                ->orderByDesc('s.subscribed_at')
                ->get(['s.id', 's.transaction_id', 's.payment_status', 's.status', 's.price', 's.paid', 'st.duration as months', 's.start_date', 's.end_date', 's.subscribed_at', 's.remark', 's.data'])
                ->map(function ($p) {
                    $p->is_manual = PaymentSource::isManual($p->data);
                    unset($p->data);
                    return $p;
                }),
            'events' => DB::table('events')->where('student_id', $id)->orderByDesc('id')->limit(50)
                ->get(['id', 'name', 'properties', 'platform', 'created_at'])
                ->map(function ($e) {
                    $e->properties = json_decode($e->properties ?? 'null', true);
                    return $e;
                }),
            'messages' => DB::table('message_sends as s')
                ->leftJoin('automations as a', 'a.id', '=', 's.automation_id')
                ->where('s.student_id', $id)
                ->orderByDesc('s.id')
                ->limit(50)
                ->get(['s.id', 's.channel', 'a.name as automation', 's.template_key', 's.variant', 's.subject', 's.status',
                    's.suppress_reason', 's.scheduled_for', 's.sent_at', 's.opened_at', 's.clicked_at', 's.goal_met_at']),
        ]);
    }

    /** GET admin/marketing/segments - saved custom segments with live counts. */
    public function segments()
    {
        $segments = DB::table('marketing_segments')->orderBy('name')->get()->map(function ($s) {
            $filters = json_decode($s->filters, true);
            return [
                'id' => $s->id,
                'name' => $s->name,
                'filters' => $filters,
                'count' => StudentFilter::apply(StudentFilter::base(), $filters)->count(),
                'created_at' => $s->created_at,
            ];
        });

        return Response::apiSuccess('Saved segments', $segments);
    }

    /** POST admin/marketing/segments {name, filters} */
    public function storeSegment(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'filters' => 'required|array',
        ]);
        $filters = StudentFilter::clean($data['filters']);
        unset($filters['segment_id']); // no nesting
        abort_if(!$filters, 422, 'Pick at least one filter before saving a segment.');

        $id = DB::table('marketing_segments')->insertGetId([
            'name' => $data['name'],
            'filters' => json_encode($filters),
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Response::apiSuccess('Segment saved', ['id' => $id, 'name' => $data['name'], 'filters' => $filters], 201);
    }

    /** DELETE admin/marketing/segments/{id} */
    public function destroySegment(int $id)
    {
        DB::table('marketing_segments')->where('id', $id)->delete();

        return Response::apiSuccess('Segment deleted');
    }

    /**
     * POST admin/marketing/students/tags {tag, action: add|remove, student_ids?: int[], filters?: {}}
     * Either explicit ids (row selection) or the current filters (whole result).
     */
    public function tags(Request $request)
    {
        $data = $request->validate([
            'tag' => ['required', 'string', 'max:50', 'regex:/^[\pL\pN _:-]+$/u'],
            'action' => 'required|in:add,remove',
            'student_ids' => 'required_without:filters|array|max:10000',
            'student_ids.*' => 'integer',
            'filters' => 'required_without:student_ids|array',
        ]);
        $tag = trim($data['tag']);

        $ids = isset($data['student_ids'])
            ? collect($data['student_ids'])
            : StudentFilter::apply(StudentFilter::base(), $data['filters'])->pluck('m.student_id');

        $affected = 0;
        foreach ($ids->unique()->chunk(1000) as $chunk) {
            if ($data['action'] === 'add') {
                $existing = DB::table('student_profiles')->whereIn('id', $chunk->all())->pluck('id');
                $affected += DB::table('student_tags')->insertOrIgnore(
                    $existing->map(fn ($sid) => ['student_id' => $sid, 'tag' => $tag, 'created_at' => now()])->all()
                );
            } else {
                $affected += DB::table('student_tags')->whereIn('student_id', $chunk->all())->where('tag', $tag)->delete();
            }
        }

        return Response::apiSuccess($data['action'] === 'add' ? "Tagged {$affected} student(s)" : "Removed tag from {$affected} student(s)", ['affected' => $affected]);
    }

    // ------------------------------------------------------------------

    private function listColumns(): array
    {
        return [
            'm.student_id', 'p.name', 'p.email', 'p.phone', 'm.exam_type_id', 'et.name as target_exam', 'm.signed_up_at',
            'm.lifecycle_stage', 'm.total_attempts', 'm.free_attempts', 'm.sprint_attempts', 'm.mock_attempts', 'm.topic_attempts',
            'm.avg_score_pct', 'm.score_trend', 'm.last_seen_at', 'm.subscription_status', 'm.subscription_ends_at',
            'm.lead_score', 'm.segments', 'p.last_platform', 'p.signup_source',
        ];
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable,2:?int} */
    private function period(Request $request): array
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'exam_type_id' => 'nullable|integer',
        ]);
        $to = $request->query('to') ? CarbonImmutable::parse($request->query('to')) : CarbonImmutable::today();
        $from = $request->query('from') ? CarbonImmutable::parse($request->query('from')) : $to->subDays(29);
        if ($from->diffInDays($to) > 730) {
            $from = $to->subDays(730);
        }

        return [$from, $to, $request->integer('exam_type_id') ?: null];
    }

    private function metricsUpdatedAt(): ?string
    {
        return DB::table('student_metrics')->max('updated_at');
    }
}
