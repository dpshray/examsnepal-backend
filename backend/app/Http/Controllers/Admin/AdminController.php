<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpdateSubRequest;
use App\Http\Resources\Doubt\AdminDoubtResource;
use App\Http\Resources\Submission\SubmissionResource;
use App\Http\Resources\Subscription\SubscriptionTypeResource;
use App\Models\Answersheet;
use App\Models\Doubt;
use App\Models\StudentExam;
use App\Models\StudentProfile;
use App\Models\Subscriber;
use App\Models\SubscriptionType;
use App\Traits\PaginatorTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminController extends Controller
{
    //
    use PaginatorTrait;
    function addorupdate(AdminUpdateSubRequest $request, $studentId)
    {
        $validated = $request->validated();
        // Fetch subscription type
        $type = SubscriptionType::findOrFail($validated['subscription_type_id']);

        // Calculate end date based on duration_days
        $startDate = now();
        $newEndDate = $startDate->copy()->addMonths($type->duration);

        $price = $type->price;
        $paid = $type->price;
        $existing = Subscriber::where('student_profile_id', $studentId)->orderBy('id', 'desc')->first();
        if (!$existing) {
            $subscriber = Subscriber::Create(
                [
                    'student_profile_id' => $studentId,
                    'subscription_type_id' => $validated['subscription_type_id'],
                    'price' => $price,
                    'paid' => $paid,
                    'paid_in_paisa' => $paid * 100 ?? 0.00,
                    'start_date' => $startDate,
                    'end_date' => $newEndDate,
                    'transaction_id' => 'TXN' . rand(10000, 99999),
                    'payment_status' => 'PAYMENT_SUCCESS', // manual add by admin
                    'status' => 1,
                    'remark' => $request->remark,
                    'data' => json_encode(['remark' => $request->remark]),
                    'subscribed_at' => now(),
                ]
            );
        } else {
            $currentEndDate = Carbon::parse($existing->end_date);
            $extendedEndDate = $currentEndDate->copy()->addMonths($type->duration);
            $existing->create([
                'student_profile_id' => $studentId,
                'subscription_type_id' => $validated['subscription_type_id'],
                'price' => $price,
                'paid' => $paid,
                'paid_in_paisa' => $paid * 100 ?? 0.00,
                'start_date' => $startDate,
                'end_date' => $extendedEndDate,
                'transaction_id' => 'TXN' . rand(10000, 99999),
                'payment_status' => 'PAYMENT_SUCCESS', // manual add by admin
                'status' => 1,
                'remark' => $request->remark,
                'data' => json_encode(['remark' => $request->remark]),
                'subscribed_at' => now(),
            ]);
            $subscriber = $existing->fresh(); // reload updated record
        }

        return response()->json([
            'message' => 'Subscription added updated successfully.',
            'subscriber' => $subscriber
        ], 200);
    }
    public function subtype(StudentProfile $student)
    {
        $rows = SubscriptionType::select('id as subscription_type_id', 'duration', 'price')
            ->where('status', 1)
            ->where('exam_type_id', $student->exam_type_id)
            ->get();
        $data = SubscriptionTypeResource::collection($rows);
        return Response::apiSuccess('Active package list', $data);
    }
    public function logoutadmin()
    {
        try {
            // Invalidate the current JWT token
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            Log::error('JWT Logout Error: ' . $e->getMessage());
            return response()->json(['error' => 'Could not log out'], 500);
        }
    }
    public function submissionsList(Request $request)
    {
        $email        = $request->query('search');
        $examTypeId   = $request->query('exam_type');
        $examCategory = $request->query('exam_category');
        $limit        = $request->input('limit', 10);

        $query = StudentExam::with(['student', 'exam.examType', 'answers']);

        if ($email) {
            $query->whereHas('student', function ($q) use ($email) {
                $q->where('email', 'like', '%' . $email . '%');
            });
        }

        if ($examTypeId) {
            $query->whereHas('exam', function ($q) use ($examTypeId) {
                $q->where('exam_type_id', $examTypeId);
            });
        }

        if ($examCategory) {
            $query->whereHas('exam', function ($q) use ($examCategory) {
                $q->where('status', $examCategory);
            });
        }

        $submissions = $query->orderBy('id', 'DESC')->paginate($limit);

        $data = $this->setupPagination($submissions, fn($item) => SubmissionResource::collection($item));

        return response()->json([
            'message' => 'Submissions list fetched successfully.',
            'submissions' => $data->data
        ], 200);
    }
    public function doubtslist(Request $request)
    {
        $limit = $request->input('limit', 10);
        $doubts = Doubt::with([
            'question' => function ($query) {
                $query->select('id', 'question', 'explanation')
                    ->with('options');
            },
            'student:id,name'
        ])
            ->orderBy('id', 'DESC')
            ->paginate($limit);


        $data = $this->setupPagination($doubts, fn($item) => AdminDoubtResource::collection($item));

        return Response::apiSuccess('doubt list', $data);

    }
}
