<?php

namespace App\Services;

use App\Enums\ExamTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\PayoutSetting;
use App\Models\StudentExam;
use App\Models\Subscriber;
use App\Models\TeacherPayout;
use App\Models\TeacherPayoutLineItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Splits amortized subscription revenue for a given month among the teachers
 * whose Sprint Quiz / Mock Test exams paying students actually completed
 * that month, per exam type.
 *
 * Simplification (v1): a subscriber's payment is spread evenly across the
 * `duration` months their plan covers, with each monthly slice attributed to
 * the calendar month containing that slice's start date (i.e. the month of
 * `start_date`, `start_date + 1 month`, `start_date + 2 months`, ...) rather
 * than prorating by exact day. Good enough for a first version; revisit if
 * exact day-level proration is needed later.
 */
class TeacherRevenueShareService
{
    private const COUNTABLE_CATEGORIES = [
        ExamTypeEnum::SPRINT_QUIZ->value,
        ExamTypeEnum::MOCK_TEST->value,
    ];

    /**
     * Read-only calculation for a period - nothing is persisted.
     *
     * @return Collection<int, array> One entry per (teacher, exam_type) with a positive share.
     */
    public function previewForPeriod(Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $poolPercentage = PayoutSetting::poolPercentage();
        $revenueByExamType = $this->amortizedRevenueByExamType($periodStart, $periodEnd);

        $results = collect();

        foreach ($revenueByExamType as $examTypeId => $revenueAmount) {
            if ($revenueAmount <= 0) {
                continue;
            }

            $poolAmount = round($revenueAmount * $poolPercentage / 100, 2);

            $payingStudentIds = $this->payingStudentIds($examTypeId, $periodStart, $periodEnd);
            $completionRows = $this->examCompletionCounts($examTypeId, $periodStart, $periodEnd, $payingStudentIds);

            $totalUniqueCompletions = (int) $completionRows->sum('unique_completions');

            if ($totalUniqueCompletions === 0) {
                continue; // no engagement to attribute this pool to
            }

            $byTeacher = $completionRows->groupBy('teacher_id');

            foreach ($byTeacher as $teacherId => $teacherRows) {
                $teacherCompletions = (int) $teacherRows->sum('unique_completions');
                $sharePercentage = round(($teacherCompletions / $totalUniqueCompletions) * 100, 4);
                $payoutAmount = round(($teacherCompletions / $totalUniqueCompletions) * $poolAmount, 2);

                $lineItems = $teacherRows->map(fn ($row) => [
                    'exam_id' => $row->exam_id,
                    'unique_completions' => (int) $row->unique_completions,
                    'contribution_percentage' => round(($row->unique_completions / $teacherCompletions) * 100, 4),
                ])->values();

                $results->push([
                    'user_id' => (int) $teacherId,
                    'exam_type_id' => (int) $examTypeId,
                    'period_start' => $periodStart->copy()->startOfMonth(),
                    'period_end' => $periodEnd->copy()->endOfMonth(),
                    'revenue_amount' => round($revenueAmount, 2),
                    'pool_percentage_used' => $poolPercentage,
                    'pool_amount' => $poolAmount,
                    'total_unique_completions' => $totalUniqueCompletions,
                    'teacher_unique_completions' => $teacherCompletions,
                    'share_percentage' => $sharePercentage,
                    'payout_amount' => $payoutAmount,
                    'line_items' => $lineItems,
                ]);
            }
        }

        return $results;
    }

    /**
     * Calculates and persists payout rows for a period as `pending`.
     * Safe to re-run: an existing row for (teacher, exam_type, period) is
     * only overwritten while still `pending` - approved/paid rows are left alone.
     */
    public function finalizeForPeriod(Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $preview = $this->previewForPeriod($periodStart, $periodEnd);

        return DB::transaction(function () use ($preview) {
            return $preview->map(function (array $row) {
                $existing = TeacherPayout::where('user_id', $row['user_id'])
                    ->where('exam_type_id', $row['exam_type_id'])
                    ->where('period_start', $row['period_start']->toDateString())
                    ->first();

                if ($existing && $existing->status !== 'pending') {
                    return $existing; // already approved/paid - do not touch
                }

                $payout = TeacherPayout::updateOrCreate(
                    [
                        'user_id' => $row['user_id'],
                        'exam_type_id' => $row['exam_type_id'],
                        'period_start' => $row['period_start']->toDateString(),
                    ],
                    [
                        'period_end' => $row['period_end']->toDateString(),
                        'revenue_amount' => $row['revenue_amount'],
                        'pool_percentage_used' => $row['pool_percentage_used'],
                        'pool_amount' => $row['pool_amount'],
                        'total_unique_completions' => $row['total_unique_completions'],
                        'teacher_unique_completions' => $row['teacher_unique_completions'],
                        'share_percentage' => $row['share_percentage'],
                        'payout_amount' => $row['payout_amount'],
                        'status' => 'pending',
                    ]
                );

                $payout->lineItems()->delete();
                $payout->lineItems()->createMany($row['line_items']);

                return $payout;
            });
        });
    }

    /**
     * Amortized subscription revenue per exam type recognized in the given
     * calendar-month period.
     *
     * @return array<int, float> [exam_type_id => revenue_amount]
     */
    private function amortizedRevenueByExamType(Carbon $periodStart, Carbon $periodEnd): array
    {
        $periodKey = $periodStart->format('Y-m');
        $revenueByExamType = [];

        Subscriber::query()
            ->where('status', 1)
            ->where('payment_status', PaymentStatusEnum::PAYMENT_SUCCESS->value)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $periodEnd)
            ->whereDate('end_date', '>=', $periodStart)
            ->with('subscriptionType')
            ->chunkById(500, function ($subscribers) use (&$revenueByExamType, $periodKey) {
                foreach ($subscribers as $subscriber) {
                    $subscriptionType = $subscriber->subscriptionType;
                    if (!$subscriptionType || !$subscriptionType->duration || !$subscriptionType->exam_type_id) {
                        continue;
                    }

                    $duration = (int) $subscriptionType->duration;
                    $monthlyAmount = ((float) $subscriber->paid) / $duration;

                    for ($i = 0; $i < $duration; $i++) {
                        $sliceMonth = $subscriber->start_date->copy()->addMonths($i)->format('Y-m');
                        if ($sliceMonth === $periodKey) {
                            $examTypeId = $subscriptionType->exam_type_id;
                            $revenueByExamType[$examTypeId] = ($revenueByExamType[$examTypeId] ?? 0) + $monthlyAmount;
                            break; // one slice per subscriber per calendar month
                        }
                    }
                }
            });

        return $revenueByExamType;
    }

    /**
     * Students with an active, paid subscription for this exam type overlapping the period.
     *
     * @return array<int>
     */
    private function payingStudentIds(int $examTypeId, Carbon $periodStart, Carbon $periodEnd): array
    {
        return Subscriber::query()
            ->where('status', 1)
            ->where('payment_status', PaymentStatusEnum::PAYMENT_SUCCESS->value)
            ->whereHas('subscriptionType', fn ($q) => $q->where('exam_type_id', $examTypeId))
            ->whereDate('start_date', '<=', $periodEnd)
            ->whereDate('end_date', '>=', $periodStart)
            ->pluck('student_profile_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Unique paying-student completions per exam (Sprint Quiz / Mock Test only)
     * for the given exam type and period.
     */
    private function examCompletionCounts(int $examTypeId, Carbon $periodStart, Carbon $periodEnd, array $payingStudentIds): Collection
    {
        if (empty($payingStudentIds)) {
            return collect();
        }

        return StudentExam::query()
            ->join('exams', 'exams.id', '=', 'student_exams.exam_id')
            ->where('exams.exam_type_id', $examTypeId)
            ->whereIn('exams.status', self::COUNTABLE_CATEGORIES)
            ->where('student_exams.is_exam_completed', 1)
            ->whereBetween('student_exams.created_at', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])
            ->whereIn('student_exams.student_id', $payingStudentIds)
            ->select('exams.id as exam_id', 'exams.user_id as teacher_id')
            ->selectRaw('COUNT(DISTINCT student_exams.student_id) as unique_completions')
            ->groupBy('exams.id', 'exams.user_id')
            ->get();
    }
}
