<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Answersheet;
use App\Models\Exam;
use Illuminate\Support\Facades\Auth;


class AnswerSheetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    /**
     * @OA\Post(
     *     path="/submit-answer",
     *     operationId="submitStudentAnswers",
     *     tags={"Quiz"},
     *     summary="Submit answers for a quiz",
     *     description="Stores answers submitted by a student for a particular exam.",
     * 
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"exam_id", "student_id", "question_id", "choosed_option_value", "correct_answer_submitted"},
     *             @OA\Property(property="exam_id", type="integer", example=1),
     *             @OA\Property(property="student_id", type="integer", example=5),
     *             @OA\Property(
     *                 property="question_id",
     *                 type="array",
     *                 @OA\Items(type="integer", example=101),
     *                 example={101, 102, 103}
     *             ),
     *             @OA\Property(
     *                 property="choosed_option_value",
     *                 type="array",
     *                 @OA\Items(type="integer", enum={1,2,3,4}, example=2),
     *                 example={2, 4, 1}
     *             ),
     *             @OA\Property(
     *                 property="correct_answer_submitted",
     *                 type="array",
     *                 @OA\Items(type="boolean", example=true),
     *                 example={true, false, true}
     *             )
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=200,
     *         description="Answers submitted successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Answers submitted successfully!"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="exam_id", type="integer", example=1),
     *                     @OA\Property(property="student_id", type="integer", example=5),
     *                     @OA\Property(property="question_id", type="integer", example=101),
     *                     @OA\Property(property="choosed_option_value", type="integer", example=2),
     *                     @OA\Property(property="correct_answer_submitted", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 )
     *             )
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=400,
     *         description="Student has already submitted answers for this exam.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Student has already submitted answers for this exam.")
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - array lengths don't match.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="All arrays (question_id, choosed_option_value, correct_answer_submitted) must have the same number of elements.")
     *         )
     *     )
     * )
     */


    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'exam_id' => 'required|integer|exists:exams,id',
            'student_id' => 'required|integer|exists:students,id',
            'question_id' => 'required|array',
            'question_id.*' => 'required|integer|exists:questions,id',
            'choosed_option_value' => 'required|array',
            'choosed_option_value.*' => 'required|string|in:1,2,3,4',
            'correct_answer_submitted' => 'required|array',
            'correct_answer_submitted.*' => 'required|boolean',
        ]);

        $alreadySubmitted = Answersheet::where('student_id', $validatedData['student_id'])
            ->where('exam_id', $validatedData['exam_id'])
            ->exists();

        if ($alreadySubmitted) {
            return response()->json([
                'message' => 'Student has already submitted answers for this exam.',
            ], 400);
        }
        $totalQuestions = count($validatedData['question_id']);
        if (
            $totalQuestions !== count($validatedData['choosed_option_value']) ||
            $totalQuestions !== count($validatedData['correct_answer_submitted'])
        ) {
            return response()->json([
                'message' => 'All arrays (question_id, choosed_option_value, correct_answer_submitted) must have the same number of elements.',
            ], 422);
        }

        $answers = [];

        for ($i = 0; $i < $totalQuestions; $i++) {
            $answers[] = [
                'exam_id' => $validatedData['exam_id'],
                'student_id' => $validatedData['student_id'],
                'question_id' => $validatedData['question_id'][$i],
                'choosed_option_value' => (int) $validatedData['choosed_option_value'][$i],
                'correct_answer_submitted' => $validatedData['correct_answer_submitted'][$i],
            ];
        }

        Answersheet::insert($answers);

        return response()->json([
            'message' => 'Answers submitted successfully!',
            'data' => $answers,
        ]);
    }


    /**
     * @OA\Get(
     *     path="/view-solutions/{exam_id}",
     *     summary="Fetch answers for a specific exam",
     *     description="Fetch all answers for the given exam for the authenticated student.",
     *     operationId="getResultsWithExam",
     *     tags={"Quiz"},
     *     @OA\Parameter(
     *         name="exam_id",
     *         in="path",
     *         required=true,
     *         description="The unique identifier of the exam",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved solutions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Solutions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="exam_id", type="integer"),
     *                     @OA\Property(property="student_id", type="integer"),
     *                     @OA\Property(property="answer", type="string"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No solutions found for this exam",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="No solutions found for this exam.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - The user is not authenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     */

    public function getResultsWithExam($exam_id)
    {
        // Check if exam exists
        $student_id = Auth::id();
        $examExists = Exam::where('id', $exam_id)->exists();

        if (!$examExists) {
            return response()->json([
                'message' => 'Exam not found.',
            ], 404);
        }

        // Fetch all answers for the given exam
        $answers = Answersheet::where('exam_id', $exam_id)
            ->where('student_id', $student_id)
            ->get();

        // need to send as Required format 




        if ($answers->isEmpty()) {
            return response()->json([
                'message' => 'No solutions found for this exam.',
            ], 404);
        }

        return response()->json([
            'message' => 'Solutions retrieved successfully',
            'data' => $answers,
        ], 200);
    }


    /**
     * @OA\Get(
     *     path="/solution/free-quiz",
     *     summary="Get Free Quizzes Done by the Student",
     *     description="Retrieve all free quizzes that have been completed by the authenticated student.",
     *     tags={"Solution"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Free quizzes retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Free quizzes retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="exam_id", type="integer", example=1),
     *                     @OA\Property(property="exam_name", type="string", example="Math Quiz"),
     *                     @OA\Property(property="status", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-26T12:00:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Something went wrong.")
     *         )
     *     )
     * )
     */
    public function getDoneFreeQuiz()
    {
        $student_id = Auth::id();
        $freeQuizzesGivenByStudent = AnswerSheet::where('student_id', $student_id)
            ->whereHas('exam', function ($query) {
                $query->where('status', 1);
            })
            ->with('exam')
            ->get();

        if ($freeQuizzesGivenByStudent->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No free quizzes found.',
                'data' => []
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Free quizzes retrieved successfully.',
            'data' => $freeQuizzesGivenByStudent
        ], 200);
    }


    /**
     * @OA\Get(
     *     path="/solution/sprint-quiz",
     *     summary="Get Sprint Quizzes Done by the Student",
     *     description="Retrieve all Sprint quizzes that have been completed by the authenticated student.",
     *     tags={"Solution"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sprint quizzes retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sprint quizzes retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="exam_id", type="integer", example=1),
     *                     @OA\Property(property="exam_name", type="string", example="Math Quiz"),
     *                     @OA\Property(property="status", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-26T12:00:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Something went wrong.")
     *         )
     *     )
     * )
     */

    public function getDoneSprintQuiz()
    {
        $student_id = Auth::id();
        $sprintQuizzesGivenByStudent = AnswerSheet::where('student_id', $student_id)
            ->whereHas('exam', function ($query) {
                $query->where('status', 3);
            })
            ->with('exam')
            ->get();
        if ($sprintQuizzesGivenByStudent->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No Sprint quizzes found.',
                'data' => []
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Sprint quizzes retrieved successfully.',
            'data' => $sprintQuizzesGivenByStudent
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/solution/mock-test",
     *     summary="Get Mock Tests Done by the Student",
     *     description="Retrieve all Mock Tests that have been completed by the authenticated student.",
     *     tags={"Solution"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Mock Tests retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Mock Tests retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="exam_id", type="integer", example=1),
     *                     @OA\Property(property="exam_name", type="string", example="Math Quiz"),
     *                     @OA\Property(property="status", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-26T12:00:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Something went wrong.")
     *         )
     *     )
     * )
     */
    public function getDoneMockTest()
    {
        $student_id = Auth::id();
        $mockTestsGivenByStudent = AnswerSheet::where('student_id', $student_id)
            ->whereHas('exam', function ($query) {
                $query->where('status', 4);
            })
            ->with('exam')
            ->get();
        if ($mockTestsGivenByStudent->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No Mock Tests found.',
                'data' => []
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mock Tests retrieved successfully.',
            'data' => $mockTestsGivenByStudent
        ], 200);
    }
}
