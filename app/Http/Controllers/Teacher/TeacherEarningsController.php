<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Resources\Teacher\TeacherPayoutDetailResource;
use App\Http\Resources\Teacher\TeacherPayoutResource;
use App\Models\TeacherPayout;
use App\Services\TeacherRevenueShareService;
use App\Traits\PaginatorTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class TeacherEarningsController extends Controller
{
    use PaginatorTrait;

    /**
     * List the logged-in teacher's finalized payouts.
     */
    /**
     * @OA\Get(
     *     path="/teacher/earnings",
     *     summary="List the teacher's payout history",
     *     operationId="teacher_earnings_list",
     *     tags={"TeacherEarnings"},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payout history")
     * )
     */
    public function index(Request $request)
    {
        $teacher = Auth::guard('users')->user();
        $perPage = $request->query('per_page', 10);

        $payouts = TeacherPayout::where('user_id', $teacher->id)
            ->with('examType:id,name')
            ->orderByDesc('period_start')
            ->paginate($perPage);

        $data = $this->setupPagination($payouts, fn ($items) => TeacherPayoutResource::collection($items))->data;

        return Response::apiSuccess('teacher earnings history', $data);
    }

    /**
     * Show one payout's full per-exam breakdown.
     */
    /**
     * @OA\Get(
     *     path="/teacher/earnings/{payout}",
     *     summary="Get one payout's detail",
     *     operationId="teacher_earnings_detail",
     *     tags={"TeacherEarnings"},
     *     @OA\Parameter(name="payout", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payout detail")
     * )
     */
    public function show(TeacherPayout $payout)
    {
        $this->isPayoutOwner($payout);

        $payout->load(['examType:id,name', 'lineItems.exam:id,exam_name']);

        return Response::apiSuccess('payout detail fetched successfully', new TeacherPayoutDetailResource($payout));
    }

    /**
     * Live, read-only estimate of the teacher's earnings for the current
     * month-to-date. Nothing here is persisted.
     */
    /**
     * @OA\Get(
     *     path="/teacher/earnings/current-estimate",
     *     summary="Live current-month earnings estimate",
     *     description="Computed on the fly from this month's data so far - not a finalized/persisted payout.",
     *     operationId="teacher_earnings_current_estimate",
     *     tags={"TeacherEarnings"},
     *     @OA\Response(response=200, description="Current estimate")
     * )
     */
    public function currentEstimate(TeacherRevenueShareService $service)
    {
        $teacher = Auth::guard('users')->user();
        $periodStart = Carbon::now()->startOfMonth();
        $periodEnd = Carbon::now();

        $preview = $service->previewForPeriod($periodStart, $periodEnd)
            ->filter(fn ($row) => $row['user_id'] === $teacher->id)
            ->values();

        $data = $preview->map(function ($row) {
            $examType = \App\Models\ExamType::find($row['exam_type_id']);

            return [
                'exam_type' => $examType ? ['id' => $examType->id, 'name' => $examType->name] : null,
                'period_start' => $row['period_start']->toDateString(),
                'as_of' => now()->toDateString(),
                'teacher_unique_completions' => $row['teacher_unique_completions'],
                'total_unique_completions' => $row['total_unique_completions'],
                'share_percentage' => $row['share_percentage'],
                'estimated_amount' => $row['payout_amount'],
            ];
        });

        return Response::apiSuccess('current period estimate', $data);
    }

    private function isPayoutOwner(TeacherPayout $payout)
    {
        throw_if(
            !Auth::user()->isAdmin() && (int) $payout->user_id !== Auth::guard('users')->id(),
            AuthorizationException::class,
            'You are not the owner'
        );
    }
}
