<?php
namespace App\Http\Controllers;

use App\Enums\ExamTypeEnum;
use App\Http\Resources\ExamCollection;
use App\Http\Resources\Student\Exam\StudentExamCompletedListCollection;
use App\Http\Resources\Student\Exam\StudentExamListCollection;
use App\Http\Resources\Student\Exam\StudentExamListResource;
use App\Models\Exam;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuizController extends Controller
{
    use PaginatorTrait;
    /**
     * @OA\Get(
     *     path="/free-quiz/completed",
     *     summary="Get Completed Free Quiz",
     *     description="Retrieve a paginated list of free quizzes.",
     *     operationId="getCompletedFreeQuiz",
     *     tags={"Quiz"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *     response=200,
     *     description="Free completed quizzes retrieved successfully",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(
     *             property="data",
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2497),
     *                     @OA\Property(property="exam_name", type="string", example="MDMS Revision All Subjects"),
     *                     @OA\Property(property="status", type="string", example="free"),
     *                     @OA\Property(property="is_negative_marking", type="boolean", example=false),
     *                     @OA\Property(property="negative_marking_point", type="integer", example=0),
     *                     @OA\Property(property="questions_count", type="integer", example=30),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=16),
     *                         @OA\Property(property="fullname", type="string", example="Loksewa preparation")
     *                     ),
     *                     @OA\Property(
     *                         property="players",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=12750),
     *                             @OA\Property(property="name", type="string", example="Bijayaa"),
     *                             @OA\Property(
     *                                 property="solutions",
     *                                 type="object",
     *                                 @OA\Property(property="corrected", type="integer", example=24)
     *                             ),
     *                             @OA\Property(property="corrected", type="integer", example=24)
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer", example=8)
     *         ),
     *         @OA\Property(property="message", type="string", example="Free completed quizzes retrieved successfully.")
     *     )
     *    )
     * )
     */
    public function getCompletedFreeQuiz()
    {
        $free_quiz_query = Exam::freeType()->authUserCompleted()->paginate();
        $data = $this->setupPagination($free_quiz_query, StudentExamCompletedListCollection::class)->data;

        return Response::apiSuccess('Free completed quizzes retrieved successfully.', $data);
    }

    /**
     * @OA\Get(
     *     path="/free-quiz/pending",
     *     summary="Get Pending Free Quiz",
     *     description="Retrieve a paginated list of free quizzes.",
     *     operationId="getPendingFreeQuiz",
     *     tags={"Quiz"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *       response=200,
     *       description="Free pending quizzes retrieved successfully.",
     *       @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(
     *           property="data",
     *           type="object",
     *           @OA\Property(
     *             property="data",
     *             type="array",
     *             @OA\Items(
     *               type="object",
     *               @OA\Property(property="id", type="integer", example=2127),
     *               @OA\Property(
     *                 property="exam_name",
     *                 type="string",
     *                 example="Physiology Quiz on Blood Pressure and Regulation"
     *               ),
     *               @OA\Property(property="is_negative_marking", type="boolean", example=false),
     *               @OA\Property(property="negative_marking_point", type="integer", example=0),
     *               @OA\Property(property="status", type="string", example="free"),
     *               @OA\Property(property="questions_count", type="integer", example=30),
     *     
     *               @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=175),
     *                 @OA\Property(property="fullname", type="string", example="Paradise Study Center")
     *               ),
     *     
     *               @OA\Property(
     *                 property="players",
     *                 type="array",
     *                 @OA\Items(
     *                   type="object",
     *                   @OA\Property(property="id", type="integer", example=12056),
     *                   @OA\Property(property="name", type="string", example="Lokaratna Gyawali"),
     *                   @OA\Property(
     *                     property="solutions",
     *                     type="object",
     *                     @OA\Property(property="corrected", type="integer", example=23)
     *                   ),
     *                   @OA\Property(property="corrected", type="integer", example=23)
     *                 )
     *               )
     *             )
     *           ),
     *     
     *           @OA\Property(property="current_page", type="integer", example=1),
     *           @OA\Property(property="last_page", type="integer", example=14),
     *           @OA\Property(property="total", type="integer", example=14)
     *         ),
     *     
     *         @OA\Property(
     *           property="message",
     *           type="string",
     *           example="Free pending quizzes retrieved successfully."
     *         )
     *       )
     *     )
     * )
     */
    public function getPendingFreeQuiz()
    {
        $free_quiz_query = Exam::freeType()->authUserPending()->paginate();
        $data = $this->setupPagination($free_quiz_query, StudentExamListCollection::class)->data;

        return Response::apiSuccess('Free pending quizzes retrieved successfully.', $data);
    }

    /**
     * @OA\Get(
     *     path="/sprint-quiz/completed",
     *     summary="Get Completed Sprint Quizzes (for users)",
     *     description="Retrieve a list of sprint quizzes. Requires an active subscription.",
     *     operationId="getCompletedSprintQuiz",
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
     *     response=200,
     *     description="Free completed quizzes retrieved successfully",
     *     @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(
     *             property="data",
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2497),
     *                     @OA\Property(property="exam_name", type="string", example="MDMS Revision All Subjects"),
     *                     @OA\Property(property="status", type="string", example="free"),
     *                     @OA\Property(property="is_negative_marking", type="boolean", example=false),
     *                     @OA\Property(property="negative_marking_point", type="integer", example=0),
     *                     @OA\Property(property="questions_count", type="integer", example=30),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=16),
     *                         @OA\Property(property="fullname", type="string", example="Loksewa preparation")
     *                     ),
     *                     @OA\Property(
     *                         property="players",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=12750),
     *                             @OA\Property(property="name", type="string", example="Bijayaa"),
     *                             @OA\Property(
     *                                 property="solutions",
     *                                 type="object",
     *                                 @OA\Property(property="corrected", type="integer", example=24)
     *                             ),
     *                             @OA\Property(property="corrected", type="integer", example=24)
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer", example=8)
     *         ),
     *         @OA\Property(property="message", type="string", example="Free completed quizzes retrieved successfully.")
     *     )
     *  )
     * )
     */
    public function getCompletedSprintQuiz()
    {
        $sprint_quiz_query = Exam::sprintType()->authUserCompleted()->paginate();
        $data = $this->setupPagination($sprint_quiz_query, StudentExamCompletedListCollection::class)->data;

        return Response::apiSuccess('Sprint completed quizzes retrieved successfully.', $data);
    }

    /**
     * @OA\Get(
     *     path="/sprint-quiz/pending",
     *     summary="Get Pending Sprint Quizzes (for users)",
     *     description="Retrieve a list of pending sprint quizzes. Requires an active subscription.",
     *     operationId="getPendingSprintQuiz",
     *     tags={"Quiz"},
     *     @OA\Response(
     *       response=200,
     *       description="Sprint pending quizzes retrieved successfully.",
     *       @OA\JsonContent(
     *         type="object",
     *     
     *         @OA\Property(property="status", type="boolean", example=true),
     *     
     *         @OA\Property(
     *           property="data",
     *           type="object",
     *     
     *           @OA\Property(
     *             property="data",
     *             type="array",
     *             @OA\Items(
     *               type="object",
     *     
     *               @OA\Property(property="id", type="integer", example=2517),
     *               @OA\Property(
     *                 property="exam_name",
     *                 type="string",
     *                 example="MDMS Microbiology Based Sprint Quiz"
     *               ),
     *               @OA\Property(property="is_negative_marking", type="boolean", example=false),
     *               @OA\Property(property="negative_marking_point", type="integer", example=0),
     *               @OA\Property(property="status", type="string", example="sprint"),
     *               @OA\Property(property="questions_count", type="integer", example=30),
     *     
     *               @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=16),
     *                 @OA\Property(property="fullname", type="string", example="Loksewa preparation")
     *               ),
     *     
     *               @OA\Property(
     *                 property="players",
     *                 type="array",
     *                 @OA\Items(
     *                   type="object",
     *                   @OA\Property(property="id", type="integer", example=12639),
     *                   @OA\Property(property="name", type="string", example="Amit Kumar chaudhary"),
     *     
     *                   @OA\Property(
     *                     property="solutions",
     *                     type="object",
     *                     @OA\Property(property="corrected", type="integer", example=29)
     *                   ),
     *     
     *                   @OA\Property(property="corrected", type="integer", example=29)
     *                 )
     *               )
     *             )
     *           ),
     *     
     *           @OA\Property(property="current_page", type="integer", example=1),
     *           @OA\Property(property="last_page", type="integer", example=7),
     *           @OA\Property(property="total", type="integer", example=73)
     *         ),
     *     
     *         @OA\Property(
     *           property="message",
     *           type="string",
     *           example="Sprint pending quizzes retrieved successfully."
     *         )
     *       )
     *     )
     * )
     */
    public function getPendingSprintQuiz()
    {
        $sprint_quiz_query = Exam::sprintType()->authUserPending()->paginate();
        $data = $this->setupPagination($sprint_quiz_query, StudentExamListCollection::class)->data;

        return Response::apiSuccess('Sprint pending quizzes retrieved successfully.', $data);
    }

    /**
     * @OA\Get(
     *     security={{ "bearerAuth":{} }},
     *     path="/mock-test/completed",
     *     summary="Get Completed Mock Tests (for users)",
     *     description="Retrieve a list of Completed Mock Tests.",
     *     operationId="getCompletedMockTests",
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
     *         description="Free completed quizzes retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2497),
     *                         @OA\Property(property="exam_name", type="string", example="MDMS Revision All Subjects"),
     *                         @OA\Property(property="status", type="string", example="free"),
     *                         @OA\Property(property="is_negative_marking", type="boolean", example=false),
     *                         @OA\Property(property="negative_marking_point", type="integer", example=0),
     *                         @OA\Property(property="questions_count", type="integer", example=30),
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=16),
     *                             @OA\Property(property="fullname", type="string", example="Loksewa preparation")
     *                         ),
     *                         @OA\Property(
     *                             property="players",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=12750),
     *                                 @OA\Property(property="name", type="string", example="Bijayaa"),
     *                                 @OA\Property(
     *                                     property="solutions",
     *                                     type="object",
     *                                     @OA\Property(property="corrected", type="integer", example=24)
     *                                 ),
     *                                 @OA\Property(property="corrected", type="integer", example=24)
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=1),
     *                 @OA\Property(property="total", type="integer", example=8)
     *             ),
     *             @OA\Property(property="message", type="string", example="Free completed quizzes retrieved successfully.")
     *         )
     *      )
     * )
     */
    public function getCompletedMockTest()
    {
        $mock_quiz_query = Exam::mockType()->authUserCompleted()->paginate();
        $data = $this->setupPagination($mock_quiz_query, StudentExamCompletedListCollection::class)->data;

        return Response::apiSuccess('Mock completed quizzes retrieved successfully.', $data);
    }

    /**
     * @OA\Get(
     *     security={{ "bearerAuth":{} }},
     *     path="/mock-test/pending",
     *     summary="Get Pending Mock Tests (for users)",
     *     description="Retrieve a list of Pending Mock Tests.",
     *     operationId="getPendingMockTests",
     *     tags={"Quiz"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *       response=200,
     *       description="Mock pending quizzes retrieved successfully.",
     *       @OA\JsonContent(
     *         type="object",
     *     
     *         @OA\Property(property="status", type="boolean", example=true),
     *     
     *         @OA\Property(
     *           property="data",
     *           type="object",
     *     
     *           @OA\Property(
     *             property="data",
     *             type="array",
     *             @OA\Items(
     *               type="object",
     *     
     *               @OA\Property(property="id", type="integer", example=2442),
     *               @OA\Property(property="exam_name", type="string", example="MDMS Mock Test"),
     *               @OA\Property(property="is_negative_marking", type="boolean", example=false),
     *               @OA\Property(property="negative_marking_point", type="integer", example=0),
     *               @OA\Property(property="status", type="string", example="mock"),
     *               @OA\Property(property="questions_count", type="integer", example=200),
     *     
     *               @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=16),
     *                 @OA\Property(property="fullname", type="string", example="Loksewa preparation")
     *               ),
     *     
     *               @OA\Property(
     *                 property="players",
     *                 type="array",
     *                 @OA\Items(
     *                   type="object",
     *                   @OA\Property(property="id", type="integer", example=12473),
     *                   @OA\Property(property="name", type="string", example="Bijay"),
     *     
     *                   @OA\Property(
     *                     property="solutions",
     *                     type="object",
     *                     @OA\Property(property="corrected", type="integer", example=156)
     *                   ),
     *     
     *                   @OA\Property(property="corrected", type="integer", example=156)
     *                 )
     *               )
     *             )
     *           ),
     *     
     *           @OA\Property(property="current_page", type="integer", example=1),
     *           @OA\Property(property="last_page", type="integer", example=7),
     *           @OA\Property(property="total", type="integer", example=81)
     *         ),
     *     
     *         @OA\Property(
     *           property="message",
     *           type="string",
     *           example="Mock pending quizzes retrieved successfully."
     *         )
     *       )
     *     )
     * )
     */
    public function getPendingMockTest()
    {
        $mock_quiz = Exam::mockType()->authUserPending()->paginate();
        $data = $this->setupPagination($mock_quiz, StudentExamListCollection::class)->data;

        return Response::apiSuccess('Mock pending quizzes retrieved successfully.', $data);
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
        $userId = Auth::guard('users')->id();
        try {
            $validatedData = $request->validate([
                'assign_id'    => 'required|integer|exists:users,id',
                'quiz_name'    => 'required|string|max:255',
                'status'       => ['required', Rule::enum(ExamTypeEnum::class)],
                'exam_type_id' => 'required|integer|exists:exam_types,id',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }
        $exam               = new Exam();
        $exam->exam_name    = $validatedData['quiz_name'];
        $exam->status       = $validatedData['status'];
        $exam->assign_id    = $validatedData['assign_id'];
        $exam->exam_type_id = $validatedData['exam_type_id'];
        $exam->user_id      = $userId;
        $exam->save();

        return response()->json([
            'message' => 'Quiz stored successfully',
            'data'    => $exam,
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

        if (! $exam) {
            return response()->json(['error' => 'Quiz not found'], 404);
        }

        try {
            $validatedData = $request->validate([
                'assign_id'    => 'required|integer|exists:users,id',
                'quiz_name'    => 'required|string|max:255',
                'status'       => 'required|integer',
                'exam_type_id' => 'required|integer|exists:exam_types,id',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }
        $exam->exam_name    = $validatedData['quiz_name'];
        $exam->status       = $validatedData['status'];
        $exam->assign_id    = $validatedData['assign_id'];
        $exam->exam_type_id = $validatedData['exam_type_id'];
        $exam->save();

        return response()->json([
            'message' => 'Quiz updated successfully',
            'data'    => $exam,
        ], 200);
    }

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
        if (! $quiz) {
            return response()->json(['error' => 'Quiz not found'], 404);
        }

        // Return the exam details
        return response()->json([
            'message' => 'Quiz found successfully',
            'data'    => $quiz,
        ], 200);
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
        if (! $exam) {
            return response()->json(['error' => 'Quiz not found'], 404);
        }

        // Try to delete the exam
        try {
            $exam->delete();
            return response()->json(['message' => 'Quiz deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to delete Quiz',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/user-free-exams-status",
     *     summary="Get user's each free exams completed status",
     *     description="Fetches list of free exams(no of free exam completed)",
     *     operationId="userFreeExamsStatus",
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
     *         description="List of completed free exams retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="exam_name", type="string", example="Math Final"),
     *                 @OA\Property(property="exam_date", type="string", format="date", example="2025-06-10"),
     *                 @OA\Property(
     *                     property="organization",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="ABC University")
     *                 ),
     *                 @OA\Property(
     *                     property="examType",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Final Exam")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function getFreeExamStatus()
    {
        $exams = Exam::whereRelation('student_exams', 'student_id', Auth::guard('api')->id())
                    ->freeType()
                    ->paginate();
        $data = $this->setupPagination($exams, ExamCollection::class)->data;

        return Response::apiSuccess("Student's free exam completed status", $data);
    }

    /**
     * @OA\Get(
     *     path="/user-sprint-exams-status",
     *     summary="Get user's each sprint exams completed status",
     *     description="Fetches list of sprint exams(no of sprint exam completed)",
     *     operationId="userSprintExamsStatus",
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
     *         description="List of completed sprint exams retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="exam_name", type="string", example="Math Final"),
     *                 @OA\Property(property="exam_date", type="string", format="date", example="2025-06-10"),
     *                 @OA\Property(
     *                     property="organization",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="ABC University")
     *                 ),
     *                 @OA\Property(
     *                     property="examType",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Final Exam")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function getSprintExamStatus()
    {
        $exams = Exam::whereRelation('student_exams', 'student_id', Auth::guard('api')->id())
                    ->sprintType()
                    ->paginate();
        $data = $this->setupPagination($exams, ExamCollection::class)->data;

        return Response::apiSuccess("Student's sprint exam completed status", $data);
    }

    /**
     * @OA\Get(
     *     path="/user-mock-exams-status",
     *     summary="Get user's each mock exams completed status",
     *     description="Fetches list of mock exams(no of mock exam completed)",
     *     operationId="userMockExamsStatus",
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
     *         description="List of completed sprint exams retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="exam_name", type="string", example="Math Final"),
     *                 @OA\Property(property="exam_date", type="string", format="date", example="2025-06-10"),
     *                 @OA\Property(
     *                     property="organization",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="ABC University")
     *                 ),
     *                 @OA\Property(
     *                     property="examType",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Final Exam")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function getMockExamStatus()
    {
        $exams = Exam::whereRelation('student_exams', 'student_id', Auth::guard('api')->id())
                    ->mockType()
                    ->paginate();
        $data = $this->setupPagination($exams, ExamCollection::class)->data;

        return Response::apiSuccess("Student's mock exam completed status", $data);
    }

    /**
     * @OA\Get(
     *     path="/total-exam-count",
     *     summary="Get total number of each exams",
     *     description="get total number of each exams.",
     *     operationId="totalExamCount",
     *     tags={"Quiz"},
     *     @OA\Response(
     *         response=200,
     *         description="Available Exams with their total",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="free",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=15),
     *                     @OA\Property(property="overall", type="integer", example=100)
     *                 ),
     *                 @OA\Property(
     *                     property="sprint",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=29),
     *                     @OA\Property(property="overall", type="integer", example=100)
     *                 ),
     *                 @OA\Property(
     *                     property="mock",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=341),
     *                     @OA\Property(property="overall", type="integer", example=0)
     *                 ),
     *                 @OA\Property(property="avg_score", type="number", format="float", example=49.01)
     *             ),
     *             @OA\Property(property="message", type="string", example="Available Exams with their total")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function totalExamCounter(){
        $free = Exam::freeType()->allAvailableExams()->count();
        $sprint = Exam::sprintType()->allAvailableExams()->count();
        $mock = Exam::mockType()->allAvailableExams()->count();

        $free_quiz_query = Exam::freeType()->authUserCompleted()->count();
        $sprint_quiz_query = Exam::sprintType()->authUserCompleted()->count();
        $mock_quiz_query = Exam::mockType()->authUserCompleted()->count();

        $free_type_performance = ($free == 0) ? 0 : ($free_quiz_query/$free)*100;
        $sprint_type_performance = ($sprint == 0) ? 0 : ($sprint_quiz_query/$sprint)*100;
        $mock_type_performance = ($mock == 0) ? 0 : ($mock_quiz_query/$mock)*100;
        


        $stats = DB::table('student_profiles as sp')
                    ->join('student_exams as se', 'sp.id', '=', 'se.student_id')
                    ->join('answersheets as a', 'se.id', '=', 'a.student_exam_id')
                    ->where('sp.id', Auth::guard('api')->id())
                    ->selectRaw('
                        COUNT(a.question_id) as total_questions,
                        SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
                    ')
                    ->first();

                $totalQuestions = $stats->total_questions ?? 0;
                $totalCorrect   = $stats->correct_answers ?? 0;

                $average_score = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 2) : 0;



        $data = [
            'free' => [
                'total' => $free,
                'overall' => round($free_type_performance, 2)
            ],
            'sprint' => [
                'total' => $sprint,
                'overall' => round($sprint_type_performance, 2)
            ],
            'mock' => [
                'total' => $mock,
                'overall' => round($mock_type_performance, 2)
            ],
            'avg_score' => $average_score
        ];
        // $data = compact('free','sprint','mock');
        return Response::apiSuccess('Available Exams with their total', $data);
    }
}
