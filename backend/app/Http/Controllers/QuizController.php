<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exam;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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
     *     summary="Get Free Quiz (for users)",
     *     description="Retrieve a paginated list of free quizzes.",
     *     operationId="getFreeQuiz",
     *     tags={"Quiz"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Free quizzes retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="exam_name", type="string", example="Math Quiz"),
     *                         @OA\Property(property="status", type="string", example="free"),
     *                         @OA\Property(property="user_id", type="integer", example=12),
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=12),
     *                             @OA\Property(property="fullname", type="string", example="John Doe")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=50)
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
            ->select(['id', 'exam_name', 'status', 'user_id'])
            ->with(['user:id,fullname'])
            ->withCount('questions')
            ->paginate(10);


        return response()->json([
            'success' => true,
            'message' => 'Free quizzes retrieved successfully.',
            'data' => $freeQuiz
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/sprint-quiz",
     *     summary="Get Sprint Quizzes (for users)",
     *     description="Retrieve a list of sprint quizzes. Requires an active subscription.",
     *     operationId="getSprintQuiz",
     *     tags={"Quiz"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
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
            ->select(['id', 'exam_name', 'status', 'user_id'])
            ->with(['user:id,fullname'])
            ->withCount('questions')
            ->paginate(10);


        return response()->json([
            'success' => true,
            'message' => 'Sprint quizzes retrieved successfully.',
            'data' => $sprintQuiz
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/mock-test",
     *     summary="Get Mock Tests (for users)",
     *     description="Retrieve a list of Mock Tests. Requires an active subscription.",
     *     operationId="getMockTests",
     *     tags={"Quiz"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
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
            ->select(['id', 'exam_name', 'status', 'user_id'])
            ->with(['user:id,fullname']) 
            ->withCount('questions')
            ->paginate(10);

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
     * @OA\Post(
     *     path="/create-quiz",
     *     summary="Create a quiz",
     *     description="Creates a quiz. (Admin)",
     *     operationId="examAsQuizStore",
     *     tags={"Quiz"},
     *     security={{"bearerAuth":{}}},
     * 
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"assign_id","quiz_name","status","exam_type_id"},
     *             @OA\Property(property="assign_id", type="integer", example=1),
     *             @OA\Property(property="quiz_name", type="string", example="Science Quiz A"),
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="exam_type_id", type="integer", example=2)
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=201,
     *         description="Quiz stored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Quiz stored successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=5),
     *                 @OA\Property(property="exam_name", type="string", example="Science Quiz A"),
     *                 @OA\Property(property="status", type="integer", example=1),
     *                 @OA\Property(property="assign_id", type="integer", example=1),
     *                 @OA\Property(property="exam_type_id", type="integer", example=2),
     *                 @OA\Property(property="user_id", type="integer", example=10),
     *                 @OA\Property(property="is_question_bank", type="integer", example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="quiz_name", type="array", @OA\Items(type="string", example="The quiz name field is required."))
     *             )
     *         )
     *     )
     * )
     */


    public function examAsQuizStore(Request $request)
    {
        $userId = Auth::id();
        try {
            $validatedData = $request->validate([
                'assign_id' => 'required|integer|exists:users,id',
                'quiz_name' => 'required|string|max:255',
                'status' => 'required|integer',
                'exam_type_id' => 'required|integer|exists:exam_types,id',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
        $exam = new Exam();
        $exam->exam_name = $validatedData['quiz_name'];
        $exam->status = $validatedData['status'];
        $exam->assign_id = $validatedData['assign_id'];
        $exam->exam_type_id = $validatedData['exam_type_id'];
        $exam->user_id = $userId;
        $exam->save();

        return response()->json([
            'message' => 'Quiz stored successfully',
            'data' => $exam,
        ], 201);
    }


    /**
     * @OA\Put(
     *     path="/update-quiz/{id}",
     *     summary="Update a Quiz (Admin)",
     *     description="Update the details of an existing quiz by its ID",
     *     operationId="updateExamAsQuiz",
     *     tags={"Quiz"},
     *     security={{"bearerAuth":{}}},
     * 
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the quiz to update",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     * 
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"assign_id", "quiz_name", "status", "exam_type_id"},
     *             @OA\Property(property="assign_id", type="integer", example=1),
     *             @OA\Property(property="quiz_name", type="string", example="Updated Quiz Name"),
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="exam_type_id", type="integer", example=2)
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=200,
     *         description="Quiz updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Quiz updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=3),
     *                 @OA\Property(property="exam_name", type="string", example="Updated Quiz Name"),
     *                 @OA\Property(property="status", type="integer", example=1),
     *                 @OA\Property(property="assign_id", type="integer", example=1),
     *                 @OA\Property(property="exam_type_id", type="integer", example=2),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=404,
     *         description="Quiz not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Quiz not found")
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="assign_id", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="quiz_name", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=500,
     *         description="Failed to update Quiz",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to update Quiz"),
     *             @OA\Property(property="message", type="string", example="Exception message...")
     *         )
     *     )
     * )
     */
    public function updateExamAsQuiz(Request $request, $id)
    {
        $exam = Exam::find($id);

        if (!$exam) {
            return response()->json(['error' => 'Quiz not found'], 404);
        }

        try {
            $validatedData = $request->validate([
                'assign_id' => 'required|integer|exists:users,id',
                'quiz_name' => 'required|string|max:255',
                'status' => 'required|integer',
                'exam_type_id' => 'required|integer|exists:exam_types,id',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $exam->exam_name = $validatedData['quiz_name'];
        $exam->status = $validatedData['status'];
        $exam->assign_id = $validatedData['assign_id'];
        $exam->exam_type_id = $validatedData['exam_type_id'];
        $exam->save();

        return response()->json([
            'message' => 'Quiz updated successfully',
            'data' => $exam,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/quiz/{id}",
     *     summary="Get a Quiz by ID (Admin)",
     *     description="Retrieve the details of a specific quiz (exam) by its ID",
     *     operationId="getQuizById",
     *     tags={"Quiz"},
     *     security={{"bearerAuth":{}}},
     * 
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the quiz to retrieve",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     * 
     *     @OA\Response(
     *         response=200,
     *         description="Quiz found successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Quiz found successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=3),
     *                 @OA\Property(property="exam_name", type="string", example="Sample Quiz"),
     *                 @OA\Property(property="status", type="integer", example=1),
     *                 @OA\Property(property="assign_id", type="integer", example=2),
     *                 @OA\Property(property="exam_type_id", type="integer", example=5),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=404,
     *         description="Quiz not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Quiz not found")
     *         )
     *     )
     * )
     */

    public function show(string $id)
    {
        //
        // Find the exam by ID
        $quiz = Exam::find($id);

        // Check if the exam exists
        if (!$quiz) {
            return response()->json(['error' => 'Quiz not found'], 404);
        }

        // Return the exam details
        return response()->json([
            'message' => 'Quiz found successfully',
            'data' => $quiz,
        ], 200);
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
    /**
     * @OA\Delete(
     *     path="/quiz/{id}",
     *     summary="Delete a Quiz by ID (Admin)",
     *     description="Delete a specific quiz (exam) by its ID",
     *     operationId="deleteQuizById",
     *     tags={"Quiz"},
     *     security={{"bearerAuth":{}}},
     * 
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the quiz to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     * 
     *     @OA\Response(
     *         response=200,
     *         description="Quiz deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Quiz deleted successfully")
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=404,
     *         description="Quiz not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Quiz not found")
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=500,
     *         description="Failed to delete Quiz",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to delete Quiz"),
     *             @OA\Property(property="message", type="string", example="Exception message...")
     *         )
     *     )
     * )
     */

    public function destroy($id)
    {
        // Find the exam by ID
        $exam = Exam::find($id);

        // Check if the exam exists
        if (!$exam) {
            return response()->json(['error' => 'Quiz not found'], 404);
        }

        // Try to delete the exam
        try {
            $exam->delete();
            return response()->json(['message' => 'Quiz deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete Quiz',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
