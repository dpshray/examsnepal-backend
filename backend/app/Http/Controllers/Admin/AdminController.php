<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpdateSubRequest;
use App\Http\Resources\Doubt\AdminDoubtResource;
use App\Http\Resources\Submission\SubmissionResource;
use App\Http\Resources\Subscription\SubscriptionTypeResource;
use App\Models\Answersheet;
use App\Models\Doubt;
use App\Models\Question;
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
    /**
     * @OA\Post(
     *     path="/add-subscriber/{studentId}",
     *     summary="Add or update a student's subscription",
     *     description="Admin can manually add or extend a student's subscription based on the selected subscription type.",
     *     operationId="AdminAddOrUpdateSubscription",
     *     tags={"Admin Subscription"},
     *     @OA\Parameter(
     *         name="studentId",
     *         in="path",
     *         required=true,
     *         description="The ID of the student to whom the subscription belongs",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Validated subscription details",
     *         @OA\JsonContent(
     *             required={"subscription_type_id"},
     *             @OA\Property(
     *                 property="subscription_type_id",
     *                 type="integer",
     *                 example=3,
     *                 description="The ID of the subscription type to assign"
     *             ),
     *             @OA\Property(
     *                 property="remark",
     *                 type="string",
     *                 example="Added manually by admin",
     *                 description="Remarks or notes for this subscription update"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Subscription added or updated successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Subscription added updated successfully."),
     *             @OA\Property(
     *                 property="subscriber",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=45),
     *                 @OA\Property(property="student_profile_id", type="integer", example=12),
     *                 @OA\Property(property="subscription_type_id", type="integer", example=3),
     *                 @OA\Property(property="price", type="number", format="float", example=499.00),
     *                 @OA\Property(property="paid", type="number", format="float", example=499.00),
     *                 @OA\Property(property="paid_in_paisa", type="integer", example=49900),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2025-11-11T09:45:00Z"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-02-11T09:45:00Z"),
     *                 @OA\Property(property="transaction_id", type="string", example="TXN12345"),
     *                 @OA\Property(property="payment_status", type="string", example="PAYMENT_SUCCESS"),
     *                 @OA\Property(property="status", type="integer", example=1),
     *                 @OA\Property(property="remark", type="string", example="Added manually by admin"),
     *                 @OA\Property(property="data", type="object", example={"remark": "Added manually by admin"}),
     *                 @OA\Property(property="subscribed_at", type="string", format="date-time", example="2025-11-11T09:45:00Z")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Subscription type or student not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\SubscriptionType]")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The subscription_type_id field is required."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     * )
     */

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
    public function resolve(Doubt $doubt, Request $request)
    {
        $request->validate([
            'remark' => 'nullable|string|max:250'
        ]);
        $question = Question::find($doubt->question_id);
        $question->update([
            'question' => $request->question,
            'explanation' => $request->explanation,
        ]);
        if ($request->has(['option_a', 'option_b', 'option_c', 'option_d'])) {
            // Remove old options
            $question->options()->delete();
            // Recreate new options
            $options = [
                ['option' => $request->option_a, 'value' => $request->option_a_is_true],
                ['option' => $request->option_b, 'value' => $request->option_b_is_true],
                ['option' => $request->option_c, 'value' => $request->option_c_is_true],
                ['option' => $request->option_d, 'value' => $request->option_d_is_true],
            ];
            $question->options()->createMany($options);
        }
        $doubt->update([
            'status' => 1,
            'remark' => $request->remark ?? null,
        ]);

        return Response::apiSuccess('Update successful');
    }
}
