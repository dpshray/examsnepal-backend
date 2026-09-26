<?php

namespace App\Services\Marketing;

use App\Enums\ExamTypeEnum;
use App\Enums\PaymentStatusEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the Marketing > Overview page. Student-level figures come
 * from student_metrics; time series come from indexed timestamp columns
 * (student_exams.submitted_at, subscribers.subscribed_at). SQL is kept
 * portable (MySQL + sqlite tests); date bucketing happens in PHP.
 */
class OverviewReport
{
    public const FUNNEL_STEPS = [
        'registered' => 'Registered',
        'first_exam' => 'Took first exam',
        'habit' => '3+ exams in 14 days',
        'value' => 'Took a Sprint or Mock',
        'intent' => 'Viewed pricing / checkout',
        'paid' => 'Paid',
        'renewed' => 'Renewed',
    ];

    private const TYPE_KEYS = [
        ExamTypeEnum::FREE_QUIZ->value => 'free',
        ExamTypeEnum::SPRINT_QUIZ->value => 'sprint',
        ExamTypeEnum::MOCK_TEST->value => 'mock',
        StudentMetricsCalculator::TOPIC_QUIZ_STATUS => 'topic',
    ];

    private CarbonImmutable $from;
    private CarbonImmutable $to;

    public function __construct(CarbonImmutable $from, CarbonImmutable $to, private ?int $examTypeId = null)
    {
        $this->from = $from->startOfDay();
        $this->to = $to->endOfDay();
    }

    public function build(): array
    {
        $days = (int) $this->from->diffInDays($this->to) + 1;
        $prevTo = $this->from->subSecond();
        $prevFrom = $prevTo->subDays($days - 1)->startOfDay();

        return [
            'period' => ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()],
            'previous_period' => ['from' => $prevFrom->toDateString(), 'to' => $prevTo->toDateString()],
            'kpis' => $this->kpisWithChange($this->kpis($this->from, $this->to), $this->kpis($prevFrom, $prevTo)),
            'funnel' => $this->funnel(),
            'daily' => $this->daily(),
            'stage_distribution' => $this->stageDistribution(),
            'top_exams' => $this->topExams(),
            'unknown_signup_students' => DB::table('student_metrics')->whereNull('signed_up_at')
                ->when($this->examTypeId, fn ($q, $v) => $q->where('exam_type_id', $v))->count(),
        ];
    }

    // ---------------------------------------------------------------- KPIs

    public function kpis(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cohort = $this->metrics()->whereBetween('signed_up_at', [$from, $to])->get(['signed_up_at', 'first_attempt_at']);
        $activated = $cohort->filter(fn ($s) => $s->first_attempt_at
            && CarbonImmutable::parse($s->first_attempt_at)->lte(CarbonImmutable::parse($s->signed_up_at)->addDays(7)))->count();

        $attemptsByType = $this->attempts()
            ->whereBetween('se.submitted_at', [$from, $to])
            ->groupBy('e.status')
            ->pluck(DB::raw('COUNT(*)'), 'e.status');
        $byType = array_fill_keys(array_values(self::TYPE_KEYS), 0);
        foreach ($attemptsByType as $status => $count) {
            if (isset(self::TYPE_KEYS[(int) $status])) {
                $byType[self::TYPE_KEYS[(int) $status]] += (int) $count;
            }
        }

        // Free -> paid: first-time payers ÷ students who attempted an exam in
        // the period and had not paid before it started.
        $firstPayers = $this->metrics()->whereBetween('first_paid_at', [$from, $to])->count();
        $unpaidActive = $this->attempts()
            ->join('student_metrics as m', 'm.student_id', '=', 'se.student_id')
            ->whereBetween('se.submitted_at', [$from, $to])
            ->where(fn ($q) => $q->whereNull('m.first_paid_at')->orWhere('m.first_paid_at', '>=', $from))
            ->distinct()->count('se.student_id');

        $payments = $this->payments()->whereBetween('s.subscribed_at', [$from, $to])
            ->get(['s.paid', 's.data', 'st.duration']);
        // Admin-added subscriptions are real sales (paid offline), so they count;
        // their share is reported separately.
        $byPlan = $payments->groupBy('duration')
            ->map(fn ($g, $months) => ['months' => (int) $months, 'count' => $g->count(), 'revenue_npr' => round((float) $g->sum('paid'), 2)])
            ->sortKeys()->values();
        $manual = $payments->filter(fn ($p) => PaymentSource::isManual($p->data));

        // Renewal: paid subscriptions that ended in the period, renewed if the
        // student made any later successful payment.
        $ended = $this->payments()->whereBetween('s.end_date', [$from->toDateString(), $to->toDateString()])
            ->get(['s.id', 's.student_profile_id', 's.subscribed_at']);
        $later = $ended->isEmpty() ? collect() : $this->payments()
            ->whereIn('s.student_profile_id', $ended->pluck('student_profile_id')->unique())
            ->get(['s.student_profile_id', 's.subscribed_at'])->groupBy('student_profile_id');
        $renewed = $ended->filter(fn ($e) => $later->get($e->student_profile_id, collect())
            ->contains(fn ($p) => $p->subscribed_at > $e->subscribed_at))->count();

        $emails = DB::table('message_sends as ms')
            ->when($this->examTypeId, fn ($q, $v) => $q->join('student_metrics as em', 'em.student_id', '=', 'ms.student_id')->where('em.exam_type_id', $v))
            ->where('ms.channel', 'email')
            ->whereBetween('ms.sent_at', [$from, $to])
            ->selectRaw('COUNT(*) as sent, SUM(CASE WHEN ms.clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked, SUM(CASE WHEN ms.goal_met_at IS NOT NULL THEN 1 ELSE 0 END) as converted')
            ->first();

        return [
            'new_registrations' => $cohort->count(),
            'activation_rate' => self::rate($activated, $cohort->count()),
            'active_students_7d' => $this->activeStudents($to->subDays(7), $to),
            'active_students_30d' => $this->activeStudents($to->subDays(30), $to),
            'attempts_free' => $byType['free'],
            'attempts_sprint' => $byType['sprint'],
            'attempts_mock' => $byType['mock'],
            'attempts_topic' => $byType['topic'],
            'free_to_paid_rate' => self::rate($firstPayers, $unpaidActive),
            'new_paid_subscriptions' => $byPlan->sum('count'),
            'revenue_npr' => round($byPlan->sum('revenue_npr'), 2),
            'revenue_by_plan' => $byPlan->all(),
            'manual_subscriptions' => $manual->count(),
            'manual_revenue_npr' => round((float) $manual->sum('paid'), 2),
            'renewal_rate' => self::rate($renewed, $ended->count()),
            'emails_sent' => (int) $emails->sent,
            'email_click_rate' => self::rate((int) $emails->clicked, (int) $emails->sent),
            // Sends whose goal (first exam, mock, payment...) happened within 72h.
            'email_attributed_conversions' => (int) $emails->converted,
        ];
    }

    private function kpisWithChange(array $current, array $previous): array
    {
        $out = [];
        foreach ($current as $key => $value) {
            $prev = $previous[$key] ?? null;
            $out[$key] = [
                'value' => $value,
                'previous' => $prev,
                'change_pct' => is_numeric($value) && is_numeric($prev) && $prev != 0
                    ? round(($value - $prev) / abs($prev) * 100, 1)
                    : null,
            ];
        }
        return $out;
    }

    // -------------------------------------------------------------- funnel

    /** Strict funnel over students who signed up in the period: each step requires the previous. */
    public function funnel(): array
    {
        $conds = [];
        $steps = [
            'registered' => '1 = 1',
            'first_exam' => 'total_attempts > 0',
            'habit' => 'habit_reached_at IS NOT NULL',
            'value' => 'first_value_at IS NOT NULL',
            'intent' => 'first_intent_at IS NOT NULL',
            'paid' => 'first_paid_at IS NOT NULL',
            'renewed' => 'payments_count >= 2',
        ];
        $selects = [];
        foreach ($steps as $key => $cond) {
            $conds[] = $cond;
            $selects[] = 'SUM(CASE WHEN ' . implode(' AND ', $conds) . " THEN 1 ELSE 0 END) as {$key}";
        }
        $row = (array) $this->metrics()->whereBetween('signed_up_at', [$this->from, $this->to])
            ->selectRaw(implode(', ', $selects))->first();

        $out = [];
        $prev = null;
        foreach (self::FUNNEL_STEPS as $key => $label) {
            $count = (int) ($row[$key] ?? 0);
            $out[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'conversion_from_previous' => $prev === null ? null : self::rate($count, $prev),
                'drop_off_pct' => $prev === null ? null : ($prev ? round(100 - $count / $prev * 100, 1) : null),
            ];
            $prev = $count;
        }
        return $out;
    }

    // ---------------------------------------------------------- time series

    /** Registrations / first exams / payments and exam-type mix per bucket (day, or week for ranges over 92 days). */
    public function daily(): array
    {
        $weekly = $this->from->diffInDays($this->to) > 92;
        $bucket = fn ($date) => $weekly
            ? CarbonImmutable::parse($date)->startOfWeek()->toDateString()
            : CarbonImmutable::parse($date)->toDateString();

        $series = [];
        foreach (CarbonPeriod::create($this->from, $weekly ? '1 week' : '1 day', $this->to) as $d) {
            $key = $bucket($d);
            $series[$key] = ['date' => $key, 'registrations' => 0, 'first_exams' => 0, 'payments' => 0, 'free' => 0, 'sprint' => 0, 'mock' => 0, 'topic' => 0];
        }
        $add = function (iterable $rows, string $field) use (&$series, $bucket) {
            foreach ($rows as $day => $count) {
                $key = $bucket($day);
                if (isset($series[$key])) {
                    $series[$key][$field] += (int) $count;
                }
            }
        };

        $add($this->countByDay($this->metrics()->whereBetween('signed_up_at', [$this->from, $this->to]), 'signed_up_at'), 'registrations');
        $add($this->countByDay($this->metrics()->whereBetween('first_attempt_at', [$this->from, $this->to]), 'first_attempt_at'), 'first_exams');
        $add($this->countByDay($this->payments()->whereBetween('s.subscribed_at', [$this->from, $this->to]), 's.subscribed_at'), 'payments');

        $mix = $this->attempts()->whereBetween('se.submitted_at', [$this->from, $this->to])
            ->groupBy(DB::raw('DATE(se.submitted_at)'), 'e.status')
            ->get([DB::raw('DATE(se.submitted_at) as day'), 'e.status', DB::raw('COUNT(*) as c')]);
        foreach ($mix as $row) {
            $key = $bucket($row->day);
            if (isset($series[$key], self::TYPE_KEYS[(int) $row->status])) {
                $series[$key][self::TYPE_KEYS[(int) $row->status]] += (int) $row->c;
            }
        }

        return ['granularity' => $weekly ? 'week' : 'day', 'series' => array_values($series)];
    }

    /** Current stage counts (not date-filtered), in precedence order. */
    public function stageDistribution(): array
    {
        $counts = $this->metrics()->groupBy('lifecycle_stage')->pluck(DB::raw('COUNT(*)'), 'lifecycle_stage');

        return collect(Lifecycle::STAGE_PRECEDENCE)
            ->map(fn ($stage) => ['stage' => $stage, 'count' => (int) ($counts[$stage] ?? 0)])
            ->all();
    }

    /** Top exams by attempts in the period, and by share of their students who paid afterwards. */
    public function topExams(int $limit = 10, int $minStudentsForConversion = 20): array
    {
        $rows = $this->attempts()
            ->join('student_metrics as m', 'm.student_id', '=', 'se.student_id')
            ->whereBetween('se.submitted_at', [$this->from, $this->to])
            ->groupBy('se.exam_id', 'e.exam_name', 'e.status')
            ->orderByDesc('attempts')
            ->limit(200)
            ->get([
                'se.exam_id', 'e.exam_name', 'e.status',
                DB::raw('COUNT(*) as attempts'),
                DB::raw('COUNT(DISTINCT se.student_id) as students'),
                DB::raw('COUNT(DISTINCT CASE WHEN m.first_paid_at > se.submitted_at THEN se.student_id END) as converted'),
            ])
            ->map(fn ($r) => [
                'exam_id' => (int) $r->exam_id,
                'exam_name' => $r->exam_name,
                'type' => self::TYPE_KEYS[(int) $r->status] ?? 'other',
                'attempts' => (int) $r->attempts,
                'students' => (int) $r->students,
                'converted' => (int) $r->converted,
                'conversion_rate' => self::rate((int) $r->converted, (int) $r->students),
            ]);

        return [
            'by_attempts' => $rows->take($limit)->values()->all(),
            'by_conversion' => $rows->where('students', '>=', $minStudentsForConversion)
                ->sortByDesc('conversion_rate')->take($limit)->values()->all(),
        ];
    }

    /**
     * Weekly signup cohorts × weeks since signup: % of the cohort that
     * submitted at least one exam in that week.
     */
    public function cohorts(int $weeks = 8): array
    {
        $start = CarbonImmutable::now()->startOfWeek()->subWeeks($weeks - 1);
        $students = $this->metrics()->where('signed_up_at', '>=', $start)->get(['student_id', 'signed_up_at']);
        $cohortOf = $students->mapWithKeys(fn ($s) => [$s->student_id => CarbonImmutable::parse($s->signed_up_at)->startOfWeek()]);

        $active = []; // cohortWeek => weekOffset => [studentId => true]
        foreach ($students->pluck('student_id')->chunk(1000) as $ids) {
            $attempts = DB::table('student_exams')->whereIn('student_id', $ids->all())
                ->where('is_exam_completed', 1)->where('submitted_at', '>=', $start)
                ->get(['student_id', 'submitted_at']);
            foreach ($attempts as $a) {
                $cohortWeek = $cohortOf[$a->student_id];
                $offset = (int) floor($cohortWeek->diffInDays(CarbonImmutable::parse($a->submitted_at)->startOfWeek()) / 7);
                if ($offset >= 0) {
                    $active[$cohortWeek->toDateString()][$offset][$a->student_id] = true;
                }
            }
        }

        $sizes = $cohortOf->map->toDateString()->countBy();
        $rows = [];
        for ($w = 0; $w < $weeks; $w++) {
            $week = $start->addWeeks($w);
            $key = $week->toDateString();
            $size = (int) ($sizes[$key] ?? 0);
            $elapsed = $weeks - $w; // weeks observable so far, including the current one
            $cells = [];
            for ($o = 0; $o < $elapsed; $o++) {
                $cells[] = $size ? round(count($active[$key][$o] ?? []) / $size * 100, 1) : null;
            }
            $rows[] = ['week_start' => $key, 'size' => $size, 'cells' => $cells];
        }
        return ['weeks' => $weeks, 'rows' => $rows];
    }

    // -------------------------------------------------------------- helpers

    private function metrics(): Builder
    {
        return DB::table('student_metrics')->when($this->examTypeId, fn ($q, $v) => $q->where('exam_type_id', $v));
    }

    /** Completed, timed, non-class attempts by app students. */
    private function attempts(): Builder
    {
        return DB::table('student_exams as se')
            ->join('exams as e', 'e.id', '=', 'se.exam_id')
            ->whereNotNull('se.student_id')
            ->where('se.is_exam_completed', 1)
            ->where(fn ($q) => $q->where('e.is_class_exam', 0)->orWhereNull('e.is_class_exam'))
            ->when($this->examTypeId, fn ($q, $v) => $q->where('e.exam_type_id', $v));
    }

    private function payments(): Builder
    {
        return DB::table('subscribers as s')
            ->join('subscription_types as st', 'st.id', '=', 's.subscription_type_id')
            ->where('s.payment_status', PaymentStatusEnum::PAYMENT_SUCCESS->value)
            ->where('s.status', 1)
            ->when($this->examTypeId, fn ($q, $v) => $q->where('st.exam_type_id', $v));
    }

    /** @return array<string,int> Y-m-d => count */
    private function countByDay(Builder $q, string $column): array
    {
        return $q->groupBy(DB::raw("DATE({$column})"))
            ->get([DB::raw("DATE({$column}) as day"), DB::raw('COUNT(*) as c')])
            ->pluck('c', 'day')
            ->all();
    }

    private function activeStudents(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->attempts()->where('se.submitted_at', '>', $from)->where('se.submitted_at', '<=', $to)
            ->distinct()->count('se.student_id');
    }

    private static function rate(int $num, int $den): ?float
    {
        return $den > 0 ? round($num / $den * 100, 1) : null;
    }
}
