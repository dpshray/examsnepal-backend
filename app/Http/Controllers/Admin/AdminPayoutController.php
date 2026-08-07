<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Payout\AdminPayoutDetailResource;
use App\Http\Resources\Admin\Payout\AdminPayoutResource;
use App\Models\TeacherPayout;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class AdminPayoutController extends Controller
{
    use PaginatorTrait;

    /**
     * List every teacher's payouts (admin sees all, unlike the teacher-facing endpoint).
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $status = $request->query('status');
        $examTypeId = $request->query('exam_type_id');
        $periodStart = $request->query('period_start'); // YYYY-MM-DD (first of month)
        $search = $request->query('search');

        $payouts = TeacherPayout::query()
            ->with(['teacher:id,username,fullname,email', 'examType:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($examTypeId, fn ($q) => $q->where('exam_type_id', $examTypeId))
            ->when($periodStart, fn ($q) => $q->where('period_start', $periodStart))
            ->when($search, fn ($q) => $q->whereHas(
                'teacher',
                fn ($tq) => $tq->where('fullname', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderByDesc('period_start')
            ->orderByDesc('payout_amount')
            ->paginate($perPage);

        $data = $this->setupPagination($payouts, fn ($items) => AdminPayoutResource::collection($items))->data;

        return Response::apiSuccess('payouts list', $data);
    }

    /**
     * Aggregate counts/amounts by status, optionally scoped to one period.
     */
    public function summary(Request $request)
    {
        $periodStart = $request->query('period_start');

        $query = TeacherPayout::query()->when($periodStart, fn ($q) => $q->where('period_start', $periodStart));

        $summary = [
            'pending_count' => (clone $query)->where('status', 'pending')->count(),
            'pending_amount' => (float) (clone $query)->where('status', 'pending')->sum('payout_amount'),
            'approved_count' => (clone $query)->where('status', 'approved')->count(),
            'approved_amount' => (float) (clone $query)->where('status', 'approved')->sum('payout_amount'),
            'paid_count' => (clone $query)->where('status', 'paid')->count(),
            'paid_amount' => (float) (clone $query)->where('status', 'paid')->sum('payout_amount'),
        ];

        return Response::apiSuccess('payouts summary', $summary);
    }

    public function show(TeacherPayout $payout)
    {
        $payout->load([
            'teacher:id,username,fullname,email',
            'examType:id,name',
            'approver:id,username,fullname',
            'lineItems.exam:id,exam_name',
        ]);

        return Response::apiSuccess('payout detail fetched successfully', new AdminPayoutDetailResource($payout));
    }

    /**
     * pending -> approved
     */
    public function approve(TeacherPayout $payout, Request $request)
    {
        if ($payout->status !== 'pending') {
            return Response::apiError('Only a pending payout can be approved.');
        }

        $validated = $request->validate([
            'remark' => 'nullable|string|max:1000',
        ]);

        $payout->update([
            'status' => 'approved',
            'approved_by' => Auth::guard('users')->id(),
            'approved_at' => now(),
            'remark' => $validated['remark'] ?? $payout->remark,
        ]);

        return Response::apiSuccess('Payout approved.', new AdminPayoutResource($payout->fresh(['teacher', 'examType'])));
    }

    /**
     * approved -> paid
     */
    public function markPaid(TeacherPayout $payout, Request $request)
    {
        if ($payout->status !== 'approved') {
            return Response::apiError('Only an approved payout can be marked as paid.');
        }

        $validated = $request->validate([
            'remark' => 'nullable|string|max:1000',
        ]);

        $payout->update([
            'status' => 'paid',
            'paid_at' => now(),
            'remark' => $validated['remark'] ?? $payout->remark,
        ]);

        return Response::apiSuccess('Payout marked as paid.', new AdminPayoutResource($payout->fresh(['teacher', 'examType'])));
    }

    /**
     * approved -> pending (undo an accidental approval; paid payouts cannot be reverted this way)
     */
    public function revert(TeacherPayout $payout)
    {
        if ($payout->status !== 'approved') {
            return Response::apiError('Only an approved (not yet paid) payout can be reverted to pending.');
        }

        $payout->update([
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return Response::apiSuccess('Payout reverted to pending.', new AdminPayoutResource($payout->fresh(['teacher', 'examType'])));
    }
}
