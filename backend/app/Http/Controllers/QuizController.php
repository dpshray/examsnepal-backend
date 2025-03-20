<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exam;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    private $quizTypes = [
        'freeQuiz' => '1',
        'sprintQuiz' => '3',
        'mockTest' => '4',
    ];

    /**
     * Display a listing of the resource.
     */

    public function index()
    {
        //
    }

    /**
     * @OA\Get(
     *     path="/free-quiz",
     *     summary="Get Free Quiz",
     *     description="Retrieve a list of free quizzes based on their status.",
     *     operationId="getFreeQuiz",
     *     tags={"Quiz"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Math Quiz"),
     *                 @OA\Property(property="status", type="string", example="free"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function getFreeQuiz()
    {
        // $freeQuiz = Exam::where('status', $this->quizTypes['free quiz'])
        // ->where('is_question_bank', true) // Filtering only where is_question_bank is true
        // ->select(['id', 'exam_name', 'status']) 
        // ->get();
        $freeQuiz = Exam::where('status', $this->quizTypes['freeQuiz'])
            ->where('is_question_bank', true)
            ->select(['id', 'exam_name', 'status', 'user_id']) // Include user_id in the selection
            ->with(['user:id,fullname']) // Eager load user name
            ->get();


        return response()->json([
            'success' => true,
            'message' => 'Free quizzes retrieved successfully.',
            'data' => $freeQuiz
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/sprint-quiz",
     *     summary="Get Sprint Quizzes",
     *     description="Retrieve a list of sprint quizzes. Requires an active subscription.",
     *     operationId="getSprintQuiz",
     *     tags={"Quiz"},
     *     security={{ "bearerAuth":{} }},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sprint quizzes retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="exam_name", type="string", example="Sprint Test 1"),
     *                     @OA\Property(property="status", type="string", example="3"),
     *                     @OA\Property(property="user", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="fullname", type="string", example="John Doe")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Subscription Inactive",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Your subscription is inactive. Please subscribe to access sprint quizzes.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */

    public function getSprintQuiz()
    {
        $student = Auth::user();
        if (!$student->is_subscripted) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription is inactive. Please subscribe to access sprint quizzes.',
            ], 403);
        }

        $sprintQuiz = Exam::where('status', $this->quizTypes['sprintQuiz'])
            ->where('is_question_bank', true)
            ->select(['id', 'exam_name', 'status', 'user_id'])
            ->with(['user:id,fullname'])  // Eager load related user details (if needed)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Sprint quizzes retrieved successfully.',
            'data' => $sprintQuiz
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/mock-test",
     *     summary="Get Mock Tests",
     *     description="Retrieve a list of Mock Tests. Requires an active subscription.",
     *     operationId="getMockTests",
     *     tags={"Quiz"},
     *     security={{ "bearerAuth":{} }},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Mock Tests retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="exam_name", type="string", example="Mock Tests 1"),
     *                     @OA\Property(property="status", type="string", example="3"),
     *                     @OA\Property(property="user", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="fullname", type="string", example="John Doe")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Subscription Inactive",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Your subscription is inactive. Please subscribe to access Mock Tests.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */

    public function getMockTest()
    {
        $student = Auth::user();
        if (!$student->is_subscripted) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription is inactive. Please subscribe to access Mock Tests.',
            ], 403);
        }

        $mockTest = Exam::where('status', $this->quizTypes['mockTest'])
            ->where('is_question_bank', true)
            ->select(['id', 'exam_name', 'status', 'user_id'])
            ->with(['user:id,fullname'])  // Eager load related user details (if needed)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Mock Tests retrieved successfully.',
            'data' => $mockTest
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
