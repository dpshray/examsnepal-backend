<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationTypeEnum;
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
use App\Services\FCMService;
use App\Traits\PaginatorTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Calculation\Statistical\Distributions\F;
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
        $existing = Subscriber::where('student_profile_id', $studentId)->where('status', 1)->orderBy('id', 'desc')->first();
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

    /**
     * @OA\Get(
     *     path="/doubtslist",
     *     summary="Get logged in student question solved doubts",
     *     tags={"Doubts"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="number of items per page",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Doubt list",
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="status",
     *                 type="boolean",
     *                 example=true
     *             ),
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="data",
     *                     type="object",
     *
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *
     *                             @OA\Property(property="id", type="integer", example=679),
     *                             @OA\Property(property="status", type="string", example="Resolved"),
     *                             @OA\Property(property="doubt", type="string", example="2 answers"),
     *                             @OA\Property(property="date", type="string", format="date-time", nullable=true),
     *                             @OA\Property(property="remark", type="string", example="C is correct"),
     *
     *                             @OA\Property(
     *                                 property="question",
     *                                 type="object",
     *
     *                                 @OA\Property(property="question", type="string", example="Becks triad is seen in"),
     *
     *                                 @OA\Property(
     *                                     property="options",
     *                                     type="array",
     *                                     @OA\Items(
     *                                         type="object",
     *                                         @OA\Property(property="id", type="integer", example=3291693),
     *                                         @OA\Property(property="question_id", type="integer", example=180938),
     *                                         @OA\Property(property="option", type="string", example="Constrictive pericarditis"),
     *                                         @OA\Property(property="value", type="integer", example=0)
     *                                     )
     *                                 ),
     *
     *                                 @OA\Property(property="explanation", type="string", example="Answer- C. Cardiac tamponade...")
     *                             ),
     *
     *                             @OA\Property(property="exam_name", type="string", example="Sprint Quiz Medicine CBQs"),
     *
     *                             @OA\Property(
     *                                 property="student",
     *                                 type="object",
     *                                 @OA\Property(property="name", type="string", example="Amit Subedi")
     *                             )
     *                         )
     *                     ),
     *
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=48),
     *                     @OA\Property(property="total", type="integer", example=478)
     *                 )
     *             ),
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="doubt list"
     *             )
     *         )
     *     )
     * )
     */
    public function doubtslist(Request $request)
    {
        $limit = $request->input('limit', 10);
        $doubts = Doubt::whereHas('question', fn($q) => $q->has('exam'))
            ->has('student')
            ->with([
                'question:id,exam_id,question,explanation', // remove closure
                'question.options',                  // load options
                'student:id,name',
                'question.exam'
            ])
            ->orderBy('id', 'DESC')
            ->paginate($limit);

        $data = $this->setupPagination($doubts, fn($item) => AdminDoubtResource::collection($item));

        return Response::apiSuccess('doubt list', $data);
    }

    /**
     * @OA\POST(
     *     path="/doubtsresolve/{id}",
     *     summary="Update a question to resolvea doubt.",
     *     description="Update a question to resolvea doubt.",
     *     operationId="AdminSolveDoubt",
     *     tags={"AdminSolveDoubt"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of doubt",
     *         @OA\Schema(type="integer", example=100)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"option_a","option_b","option_c","option_d"},
     *                 @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *                 @OA\Property(property="explanation", type="string", example="Kathmandu is the capital city."),
     *                 @OA\Property(property="option_a_id", type="integer", example=12345),
     *                 @OA\Property(property="option_b_id", type="integer", example=12345),
     *                 @OA\Property(property="option_c_id", type="integer", example=12345),
     *                 @OA\Property(property="option_d_id", type="integer", example=12345),
     *                 @OA\Property(property="option_a", type="string", example="Kathmandu"),
     *                 @OA\Property(property="option_a_is_true", type="boolean", example=true),
     *                 @OA\Property(property="option_b", type="string", example="Pokhara"),
     *                 @OA\Property(property="option_b_is_true", type="boolean", example=false),
     *                 @OA\Property(property="option_c", type="string", example="Lalitpur"),
     *                 @OA\Property(property="option_c_is_true", type="boolean", example=false),
     *                 @OA\Property(property="option_d", type="string", example="Bhaktapur"),
     *                 @OA\Property(property="option_d_is_true", type="boolean", example=false),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question Updated Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="Question updated for exam: Example Exam Name")
     *         )
     *     )
     * )
     */
    public function resolve(Doubt $doubt, Request $request)
    {
        $form_data = $request->validate([
            "question" => 'required',
            "explanation" => 'required',
            "option_d_id" => 'required|exists:option_questions,id',
            "option_c_id" => 'required|exists:option_questions,id',
            "option_b_id" => 'required|exists:option_questions,id',
            "option_a_id" => 'required|exists:option_questions,id',
            "option_a" => 'required|max:255',
            "option_b" => 'required|max:255',
            "option_c" => 'required|max:255',
            "option_d" => 'required|max:255',
            "image" => 'sometimes|nullable|image',
            "option_a_is_true" => 'required|boolean',
            "option_b_is_true" => 'required|boolean',
            "option_c_is_true" => 'required|boolean',
            "option_d_is_true" => 'required|boolean',
            'remark' => 'nullable|string|max:250'
        ]);

        $question = $doubt->question;
        $request_option_id_not_match_with_existing_question_option_id = $question->options
            ->pluck('id')
            ->diff($request->only([
                "option_a_id",
                "option_b_id",
                "option_c_id",
                "option_d_id",
            ]))
            ->isNotEmpty();
        if ($request_option_id_not_match_with_existing_question_option_id) {
            return Response::apiError("The selected option does not belong to this question.");
        }

        DB::transaction(function () use($doubt, $question, $form_data) {
            $question->update([
                'question' => $form_data['question'],
                'explanation' => $form_data['explanation'],
            ]);
            $options = [
                ['option_id' => $form_data['option_a_id'], 'option' => $form_data['option_a'], 'value' => $form_data['option_a_is_true']],
                ['option_id' => $form_data['option_b_id'], 'option' => $form_data['option_b'], 'value' => $form_data['option_b_is_true']],
                ['option_id' => $form_data['option_c_id'], 'option' => $form_data['option_c'], 'value' => $form_data['option_c_is_true']],
                ['option_id' => $form_data['option_d_id'], 'option' => $form_data['option_d'], 'value' => $form_data['option_d_is_true']],
            ];
            foreach ($options as $option) {
                $question->options()->where('id', $option['option_id'])
                    ->update([
                        'option' => $option['option'],
                        'value' => $option['value']
                    ]);
            }
            $doubt->update([
                'status' => 0,
                'remark' => $request->remark ?? null,
            ]);
            $fcmService = new FCMService(
                'Doubt Resolved',
                'Your doubt for question ID ' . $doubt->question_id . ' has been resolved.',
                NotificationTypeEnum::DOUBT_RESOLVED->value,
                [$doubt->student->id]
            );
            $fcmService->notify([$doubt->student->fcm_token]);
        });
        return Response::apiSuccess('Doubt question updated successfully.');
    }
}
