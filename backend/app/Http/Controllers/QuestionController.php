<?php
namespace App\Http\Controllers;

use App\Enums\ExamTypeEnum;
use App\Http\Resources\QuestionCollection;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Question;
use App\Models\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class QuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    /**
     * @OA\Get(
     *     path="/questions",
     *     operationId="getQuestions",
     *     tags={"MCQs"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     summary="Get questions based on user exam type with pagination",
     *     description="Fetches questions for the authenticated user based on their exam type, with pagination support.",
     *     @OA\Response(
     *         response=200,
     *         description="Questions retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Questions retrieved successfully!"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="question", type="string", example="What is PHP?"),
     *                     @OA\Property(property="exam_id", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="per_page", type="integer", example=25),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=4),
     *                 @OA\Property(property="next_page_url", type="string", example="http://your-api.com/api/questions?page=2"),
     *                 @OA\Property(property="prev_page_url", type="string", example="null")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Student profile not found or no exams found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Student profile not found")
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     */

    public function index()
    {
        // Get authenticated user
        $user = Auth::guard('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Fetch student's profile
        // $studentProfile = StudentProfile::find($user->id);
        // if (! $studentProfile) {
        //     return response()->json(['message' => 'Student profile not found'], 404);
        // }
        $userExamTypeId = $user->exam_type_id;
        $examType     = ExamType::find($userExamTypeId);
        if (! $examType) {
            return response()->json([
                'success' => false,
                'message' => 'Exam type not found in the system.',
            ], 404);
        }
        $examIds = Exam::where('exam_type_id', $userExamTypeId)->pluck('id');
        if ($examIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No exams found for this exam type.',
            ], 404);
        }
        // Get all questions that belong to these exams
        $questions = Question::with('options')->whereIn('exam_id', $examIds)->paginate(10);
        return response()->json([
            'success' => true,
            'message' => 'Questions retrieved successfully!',
            'data'    => $questions,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/questions/all",
     *     operationId="getAllQuestions",
     *     tags={"MCQs"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     summary="Get all questions",
     *     description="Fetches all the questions from the database.",
     *     @OA\Response(
     *         response=200,
     *         description="Questions retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Questions retrieved successfully!"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="question", type="string", example="What is PHP?"),
     *                     @OA\Property(property="exam_id", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No questions found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No questions found")
     *         )
     *     )
     * )
     */

    public function getAllQuestion()
    {
        $questions = Question::paginate(25);
        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No questions found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Questions retrieved successfully!',
            'data'    => $questions,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/search-questions",
     *     summary="Search MCQs by keyword",
     *     description="Searches for multiple-choice questions (MCQs) based on a keyword using Full-Text Search.",
     *     operationId="searchQuestions",
     *     tags={"MCQs"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="pagination",
     *         @OA\Schema(
     *             type="string",
     *             example="1"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="keyword",
     *         in="query",
     *         required=true,
     *         description="The keyword to search for MCQs",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Questions retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Questions retrieved successfully!"),
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
     *                         @OA\Property(property="question", type="string", example="What is Laravel?"),
     *                         @OA\Property(property="options", type="array", @OA\Items(type="string")),
     *                         @OA\Property(property="correct_answer", type="string", example="A PHP framework"),
     *                         @OA\Property(property="exam_id", type="integer", example=1)
     *                     )
     *                 ),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=50)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Keyword is required",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Keyword is required")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="No questions found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No questions found")
     *         )
     *     )
     * )
     */
    public function searchQuestions(Request $request)
    {
        $user = Auth::guard('api')->user();
        $exam_type_id = $user->exam_type_id;

        // if (! $user) {
        //     return response()->json(['message' => 'Unauthorized'], 401);
        // }

        // Fetch the student's profile
        // $studentProfile = StudentProfile::find($user->id);
        // if (! $studentProfile) {
        //     return response()->json(['message' => 'Student profile not found'], 404);
        // }

        // $userExamType = $studentProfile->exam_type;
        // $examType     = ExamType::where('name', $userExamType)->first();
        // if (! $examType) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Exam type not found in the system.',
        //     ], 404);
        // }

        // return $examIds = Question::whereIn('exam_id',Exam::where('exam_type_id', $user->exam_type_id)->pluck('id')->toArray())->where('question','like','%what%')->count();
        // if ($examIds->isEmpty()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'No exams found for this exam type.',
        //     ], 404);
        // }

        $keyword = $request->query('keyword'); // Get the keyword from the query parameter
        if (! $keyword) {
            return response()->json(['message' => 'Keyword is required'], 400);
        }
        if (strlen($keyword) < 3) {
            return response()->json([
                'success' => false,
                'message' => 'Keyword must be at least 3 characters long.',
            ], 400);
        }
        // Use LIKE for flexible partial text search within the user's exam_ids
        $questions = Question::with('options')->whereRelation('exam','exam_type_id','=', $exam_type_id)
                        ->where('question', 'LIKE', '%' . $keyword . '%')
                        ->paginate(10);

        // $pagination_data = $questions->toArray();
        // ['links' => $links] = $pagination_data;


        $data['data'] = new QuestionCollection($questions);
        $data['current_page'] = $questions->currentPage();
        $data['last_page']    = $questions->lastPage();
        $data['total']        = $questions->total();

        // $data = compact('data');

        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No Results Found...',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Questions retrieved successfully!',
            'data'    => $data,
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
    /**
     * @OA\Post(
     *     path="/questions",
     *     summary="Create a new question To Exams",
     *     description="This endpoint allows users to create a new question To Exams.",
     *     operationId="storeQuestion",
     *     tags={"MCQs"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"exam_id","subject_id", "question", "option_1", "option_value_1", "option_2", "option_value_2"},
     *                 @OA\Property(property="exam_id", type="integer", example=1),
     *                 @OA\Property(property="subject_id", type="integer", example=1),
     *                 @OA\Property(property="question", type="string", maxLength=255, example="What is the capital of Nepal?"),
     *                 @OA\Property(property="option_1", type="string", maxLength=255, example="Kathmandu"),
     *                 @OA\Property(property="option_value_1", type="boolean", example=true),
     *                 @OA\Property(property="option_2", type="string", maxLength=255, example="Pokhara"),
     *                 @OA\Property(property="option_value_2", type="boolean", example=false),
     *                 @OA\Property(property="option_3", type="string", maxLength=255, example="Lalitpur", nullable=true),
     *                 @OA\Property(property="option_value_3", type="boolean", example=true, nullable=true),
     *                 @OA\Property(property="option_4", type="string", maxLength=255, example="Bhaktapur", nullable=true),
     *                 @OA\Property(property="option_value_4", type="boolean", example=false, nullable=true),
     *                 @OA\Property(property="explanation", type="string", nullable=true, example="Kathmandu is the capital of Nepal."),
     *                 @OA\Property(property="subject", type="string", maxLength=255, nullable=true, example="Geography"),
     *                 @OA\Property(property="exam_type", type="string", maxLength=255, nullable=true, example="MCQ"),
     *                 @OA\Property(property="remark", type="string", maxLength=255, nullable=true, example="Important question"),
     *                 @OA\Property(property="serial", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="old_exam_id", type="integer", nullable=true, example=123),
     *                 @OA\Property(property="uploader", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="mark_type", type="string", maxLength=255, nullable=true, example="Numerical")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Question created successfully!",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Question created successfully!"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="exam_id", type="integer", example=1),
     *                 @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *                 @OA\Property(property="option_1", type="string", example="Kathmandu"),
     *                 @OA\Property(property="option_value_1", type="boolean", example=true),
     *                 @OA\Property(property="option_2", type="string", example="Pokhara"),
     *                 @OA\Property(property="option_value_2", type="boolean", example=false),
     *                 @OA\Property(property="option_3", type="string", nullable=true, example="Lalitpur"),
     *                 @OA\Property(property="option_value_3", type="boolean", nullable=true, example=true),
     *                 @OA\Property(property="option_4", type="string", nullable=true, example="Bhaktapur"),
     *                 @OA\Property(property="option_value_4", type="boolean", nullable=true, example=false),
     *                 @OA\Property(property="explanation", type="string", nullable=true, example="Kathmandu is the capital of Nepal."),
     *                 @OA\Property(property="subject", type="string", nullable=true, example="Geography"),
     *                 @OA\Property(property="exam_type", type="string", nullable=true, example="MCQ"),
     *                 @OA\Property(property="remark", type="string", nullable=true, example="Important question"),
     *                 @OA\Property(property="serial", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="old_exam_id", type="integer", nullable=true, example=123),
     *                 @OA\Property(property="uploader", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="mark_type", type="string", nullable=true, example="Numerical")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to create Question",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create Question. Please try again."),
     *             @OA\Property(property="error", type="string", example="Database error or other internal error")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Validate incoming request data
        $validatedData = $request->validate([
            'exam_id'        => 'required|exists:exams,id',
            'subject_id'     => 'required|exists:subjects,id',
            'question'       => 'required|string|max:255',
            'option_1'       => 'required|string|max:255',
            'option_value_1' => 'required|boolean',
            'option_2'       => 'required|string|max:255',
            'option_value_2' => 'required|boolean',
            'option_3'       => 'nullable|string|max:255',
            'option_value_3' => 'nullable|boolean',
            'option_4'       => 'nullable|string|max:255',
            'option_value_4' => 'nullable|boolean',
            'explanation'    => 'nullable|string',
            'subject'        => 'nullable|string|max:255',
            'exam_type'      => 'nullable|string|max:255',
            'remark'         => 'nullable|string|max:255',
            'serial'         => 'nullable|integer',
            'old_exam_id'    => 'nullable|integer',
            'uploader'       => 'nullable|exists:users,id',
            'mark_type'      => 'nullable|string|max:255',
        ]);

        // Ensure only one `option_value_*` is true
        $trueOptionCount = collect([
            $validatedData['option_value_1'],
            $validatedData['option_value_2'],
            $validatedData['option_value_3'] ?? false,
            $validatedData['option_value_4'] ?? false,
        ])->filter(function ($value) {
            return $value === true;
        })->count();

        if ($trueOptionCount !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Exactly one option must have a true value.',
            ], 422); // HTTP 422 Unprocessable Entity
        }

        try {
            // Create a new Question record
            $question = Question::create($validatedData);

            // Return a success response
            return response()->json([
                'success' => true,
                'message' => 'Question created successfully!',
                'data'    => $question, // The `data` field is directly included here
            ], 201);                // HTTP 201 Created
        } catch (\Exception $e) {
            // Return a failure response if there's an error
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Question. Please try again.',
                'error'   => $e->getMessage(),
            ], 500); // HTTP 500 Internal Server Error
        }
    }

    /**
     * @OA\Post(
     *     path="/question-bank/questions",
     *     summary="Create a new question for the question bank",
     *     description="This endpoint allows users to create a new question in a valid question bank. The exam must be a question bank.",
     *     operationId="storeOnQuestionBank",
     *     tags={"MCQs"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"exam_id","subject_id", "question", "option_1", "option_value_1", "option_2", "option_value_2"},
     *
     *                 @OA\Property(property="exam_id", type="integer", example=1),
     *                 @OA\Property(property="subject_id", type="integer", example=1),
     *                 @OA\Property(property="question", type="string", maxLength=255, example="What is the capital of Nepal?"),
     *                 @OA\Property(property="option_1", type="string", maxLength=255, example="Kathmandu"),
     *                 @OA\Property(property="option_value_1", type="boolean", example=true),
     *                 @OA\Property(property="option_2", type="string", maxLength=255, example="Pokhara"),
     *                 @OA\Property(property="option_value_2", type="boolean", example=false),
     *                 @OA\Property(property="option_3", type="string", maxLength=255, example="Lalitpur", nullable=true),
     *                 @OA\Property(property="option_value_3", type="boolean", example=true, nullable=true),
     *                 @OA\Property(property="option_4", type="string", maxLength=255, example="Bhaktapur", nullable=true),
     *                 @OA\Property(property="option_value_4", type="boolean", example=false, nullable=true),
     *                 @OA\Property(property="explanation", type="string", nullable=true, example="Kathmandu is the capital of Nepal."),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Question created successfully in the question bank.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Question created successfully!"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="exam_id", type="integer", example=1),
     *                 @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *                 @OA\Property(property="option_1", type="string", example="Kathmandu"),
     *                 @OA\Property(property="option_value_1", type="boolean", example=true),
     *                 @OA\Property(property="option_2", type="string", example="Pokhara"),
     *                 @OA\Property(property="option_value_2", type="boolean", example=false),
     *                 @OA\Property(property="option_3", type="string", nullable=true, example="Lalitpur"),
     *                 @OA\Property(property="option_value_3", type="boolean", nullable=true, example=true),
     *                 @OA\Property(property="option_4", type="string", nullable=true, example="Bhaktapur"),
     *                 @OA\Property(property="option_value_4", type="boolean", nullable=true, example=false),
     *                 @OA\Property(property="explanation", type="string", nullable=true, example="Kathmandu is the capital of Nepal."),
     *                 @OA\Property(property="subject", type="string", nullable=true, example="Geography"),
     *                 @OA\Property(property="exam_type", type="string", nullable=true, example="MCQ"),
     *                 @OA\Property(property="remark", type="string", nullable=true, example="Important question"),
     *                 @OA\Property(property="serial", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="old_exam_id", type="integer", nullable=true, example=123),
     *                 @OA\Property(property="mark_type", type="string", nullable=true, example="Numerical"),
     *                 @OA\Property(property="uploader", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="from_question_bank", type="boolean", nullable=true, example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Unable to find question bank.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unable to find question bank.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Exactly one option must have a true value."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create Question. Please try again."),
     *             @OA\Property(property="error", type="string", example="Database error or other internal error")
     *         )
     *     )
     * )
     */

    public function storeOnQuestionBank(Request $request)
    {
        // Validate incoming request data
        $userId        = Auth::id();
        $validatedData = $request->validate([
            'exam_id'        => 'required|exists:exams,id',
            'subject_id'     => 'required|exists:subjects,id',
            'question'       => 'required|string|max:255',
            'option_1'       => 'required|string|max:255',
            'option_value_1' => 'required|boolean',
            'option_2'       => 'required|string|max:255',
            'option_value_2' => 'required|boolean',
            'option_3'       => 'nullable|string|max:255',
            'option_value_3' => 'nullable|boolean',
            'option_4'       => 'nullable|string|max:255',
            'option_value_4' => 'nullable|boolean',
            'explanation'    => 'nullable|string',

        ]);

        // Ensure only one `option_value_*` is true
        $trueOptionCount = collect([
            $validatedData['option_value_1'],
            $validatedData['option_value_2'],
            $validatedData['option_value_3'] ?? false,
            $validatedData['option_value_4'] ?? false,
        ])->filter(function ($value) {
            return $value === true;
        })->count();

        if ($trueOptionCount !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Exactly one option must have a true value.',
            ], 422);
        }

        try {
            $exam = Exam::findOrFail($validatedData['exam_id']);

            if ($exam->is_question_bank !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to find question bank.',
                ], 404);
            }

            $validatedData['exam_id']            = $exam->id;
            $validatedData['uploader']           = $userId;
            $validatedData['from_question_bank'] = true;

            $question = Question::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Question created successfully!',
                'data'    => $question,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Question. Please try again.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/questions/{id}",
     *     summary="Get a question by ID",
     *     description="This endpoint allows users to retrieve a specific question by its ID.",
     *     operationId="showQuestion",
     *     tags={"MCQs"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The ID of the question to retrieve",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             example="1"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Question retrieved successfully!"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="exam_id", type="integer", example=1),
     *                 @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *                 @OA\Property(property="option_1", type="string", example="Kathmandu"),
     *                 @OA\Property(property="option_value_1", type="boolean", example=true),
     *                 @OA\Property(property="option_2", type="string", example="Pokhara"),
     *                 @OA\Property(property="option_value_2", type="boolean", example=false),
     *                 @OA\Property(property="option_3", type="string", nullable=true, example="Lalitpur"),
     *                 @OA\Property(property="option_value_3", type="boolean", nullable=true, example=true),
     *                 @OA\Property(property="option_4", type="string", nullable=true, example="Bhaktapur"),
     *                 @OA\Property(property="option_value_4", type="boolean", nullable=true, example=false),
     *                 @OA\Property(property="explanation", type="string", nullable=true, example="Kathmandu is the capital of Nepal."),
     *                 @OA\Property(property="subject", type="string", nullable=true, example="Geography"),
     *                 @OA\Property(property="exam_type", type="string", nullable=true, example="MCQ"),
     *                 @OA\Property(property="remark", type="string", nullable=true, example="Important question"),
     *                 @OA\Property(property="serial", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="old_exam_id", type="integer", nullable=true, example=123),
     *                 @OA\Property(property="uploader", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="mark_type", type="string", nullable=true, example="Numerical")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Question not found"),
     *             @OA\Property(property="error", type="string", example="No question found with the given ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to retrieve the question",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve the question. Please try again."),
     *             @OA\Property(property="error", type="string", example="Database error or other internal error")
     *         )
     *     )
     * )
     */
    public function show(string $id)
    {
        try {
            // Fetch the question from the database by ID
            $question = Question::findOrFail($id);

            // Return the success response with the question data
            return response()->json([
                'success' => true,
                'message' => 'Question retrieved successfully!',
                'data'    => $question,
            ], 200); // HTTP 200 OK
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Return a failure response if the question is not found
            return response()->json([
                'success' => false,
                'message' => 'Question not found',
                'error'   => $e->getMessage(),
            ], 404); // HTTP 404 Not Found
        } catch (\Exception $e) {
            // Return a failure response for other errors
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve the question. Please try again.',
                'error'   => $e->getMessage(),
            ], 500); // HTTP 500 Internal Server Error
        }
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

    /**
     * @OA\Put(
     *     path="/questions/{id}",
     *     summary="Update a question",
     *     description="Update an existing question by ID.",
     *     operationId="updateExamQuestion",
     *     tags={"MCQs"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The ID of the question to update",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="exam_id", type="integer", example=1),
     *             @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *             @OA\Property(property="option_1", type="string", example="Kathmandu"),
     *             @OA\Property(property="option_value_1", type="boolean", example=true),
     *             @OA\Property(property="option_2", type="string", example="Pokhara"),
     *             @OA\Property(property="option_value_2", type="boolean", example=false),
     *             @OA\Property(property="option_3", type="string", nullable=true, example="Lalitpur"),
     *             @OA\Property(property="option_value_3", type="boolean", nullable=true, example=true),
     *             @OA\Property(property="option_4", type="string", nullable=true, example="Bhaktapur"),
     *             @OA\Property(property="option_value_4", type="boolean", nullable=true, example=false),
     *             @OA\Property(property="explanation", type="string", nullable=true, example="Kathmandu is the capital of Nepal."),
     *             @OA\Property(property="subject", type="string", nullable=true, example="Geography"),
     *             @OA\Property(property="exam_type", type="string", nullable=true, example="MCQ"),
     *             @OA\Property(property="remark", type="string", nullable=true, example="Important question"),
     *             @OA\Property(property="serial", type="integer", nullable=true, example=1),
     *             @OA\Property(property="old_exam_id", type="integer", nullable=true, example=123),
     *             @OA\Property(property="uploader", type="integer", nullable=true, example=1),
     *             @OA\Property(property="mark_type", type="string", nullable=true, example="Numerical")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Question updated successfully!"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Question not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to update the question",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update the question. Please try again.")
     *         )
     *     )
     * )
     */
    public function update(Request $request, string $id)
    {
        try {
            // Validate incoming request data
            $validatedData = $request->validate([
                'exam_id'        => 'required|exists:exams,id',
                'question'       => 'required|string|max:255',
                'option_1'       => 'required|string|max:255',
                'option_value_1' => 'required|boolean',
                'option_2'       => 'required|string|max:255',
                'option_value_2' => 'required|boolean',
                'option_3'       => 'nullable|string|max:255',
                'option_value_3' => 'nullable|boolean',
                'option_4'       => 'nullable|string|max:255',
                'option_value_4' => 'nullable|boolean',
                'explanation'    => 'nullable|string',
                'subject'        => 'nullable|string|max:255',
                'exam_type'      => 'nullable|string|max:255',
                'remark'         => 'nullable|string|max:255',
                'serial'         => 'nullable|integer',
                'old_exam_id'    => 'nullable|integer',
                'uploader'       => 'nullable|exists:users,id',
                'mark_type'      => 'nullable|string|max:255',
            ]);

            // Ensure exactly one option is true
            $trueOptionCount = collect([
                $validatedData['option_value_1'],
                $validatedData['option_value_2'],
                $validatedData['option_value_3'] ?? false,
                $validatedData['option_value_4'] ?? false,
            ])->filter(function ($value) {
                return $value === true;
            })->count();

            if ($trueOptionCount !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exactly one option must have a true value.',
                ], 422); // HTTP 422 Unprocessable Entity
            }

            // Find the question by ID
            $question = Question::find($id);

            if (! $question) {
                return response()->json([
                    'success' => false,
                    'message' => 'Question not found',
                ], 404); // HTTP 404 Not Found
            }

            // Update the question with validated data
            $question->update($validatedData);

            // Return a success response
            return response()->json([
                'success' => true,
                'message' => 'Question updated successfully!',
                'data'    => $question,
            ], 200); // HTTP 200 OK

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update the question. Please try again.',
                'error'   => $e->getMessage(),
            ], 500); // HTTP 500 Internal Server Error
        }
    }

    /**
     * @OA\Get(
     *     path="/free-quiz/questions/{exam_id}",
     *     summary="Get all active questions for a specific exam",
     *     description="This endpoint retrieves all active questions (status = 1) for a given exam by its ID.",
     *     operationId="freeQuizQuestions",
     *     tags={"Quiz"},
     *    @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="exam_id",
     *         in="path",
     *         required=true,
     *         description="ID of the exam to retrieve questions for",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="query",
     *         required=false,
     *         description="pagination",
     *         @OA\Schema(
     *             type="integer",
     *             example="1"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Questions retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Questions retrieved successfully!"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="exam_id", type="integer", example=1),
     *                     @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *                     @OA\Property(property="option_1", type="string", example="Kathmandu"),
     *                     @OA\Property(property="option_value_1", type="boolean", example=true),
     *                     @OA\Property(property="option_2", type="string", example="Pokhara"),
     *                     @OA\Property(property="option_value_2", type="boolean", example=false),
     *                     @OA\Property(property="option_3", type="string", nullable=true, example="Lalitpur"),
     *                     @OA\Property(property="option_value_3", type="boolean", nullable=true, example=true),
     *                     @OA\Property(property="option_4", type="string", nullable=true, example="Bhaktapur"),
     *                     @OA\Property(property="option_value_4", type="boolean", nullable=true, example=false),
     *                     @OA\Property(property="explanation", type="string", nullable=true, example="Kathmandu is the capital of Nepal."),
     *                     @OA\Property(property="subject", type="string", nullable=true, example="Geography"),
     *                     @OA\Property(property="exam_type", type="string", nullable=true, example="MCQ"),
     *                     @OA\Property(property="remark", type="string", nullable=true, example="Important question"),
     *                     @OA\Property(property="serial", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="old_exam_id", type="integer", nullable=true, example=123),
     *                     @OA\Property(property="mark_type", type="string", nullable=true, example="Numerical")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No active questions found for the specified exam ID",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No active questions found for this exam.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve questions. Please try again."),
     *             @OA\Property(property="error", type="string", example="Database error or other internal error")
     *         )
     *     )
     * )
     */
    public function freeQuizQuestions(Exam $exam_id)
    {
        $exam = $exam_id;
        // Check if the exam exists and has an active status
        if ($exam->status != ExamTypeEnum::FREE_QUIZ->value || $exam->exam_type_id != Auth::guard('api')->user()->exam_type_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Exam Id for Free Quiz Questions',
            ], 404);
        }

        $first_time_token = null;
        try {
            $first_time_token = $this->checkIfExamHasBeenStartedPreviously($exam, request('token', null));
        } catch (\Exception $e) {
            return Response::apiError($e->getMessage(), null, 409);
        }

        $questions = $exam->questions()
            ->with('options')
            ->select('id', 'exam_id', 'question', 'explanation')
            ->paginate(10);

        $pagination_data = $questions->toArray();

        ['links' => $links] = $pagination_data;
        $data               = new QuestionCollection($questions);

        $links['current_page'] = $questions->currentPage();
        $links['last_page']    = $questions->lastPage();
        $links['total']        = $questions->total();
        $token                 = $first_time_token;

        $data = compact('data', 'links', 'token');

        return Response::apiSuccess('Questions retrieved successfully!', $data);
    }

    /**
     * @OA\Get(
     *     path="/mock-test/questions/{exam_id}",
     *     summary="Get all active questions for a specific exam",
     *     description="This endpoint retrieves all active questions (status = 4) for a given exam by its ID.",
     *     operationId="mockTestQuestions",
     *     tags={"Quiz"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="exam_id",
     *         in="path",
     *         required=true,
     *         description="ID of the exam to retrieve questions for",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="query",
     *         required=false,
     *         description="pagination token",
     *         @OA\Schema(
     *             type="string",
     *             example="kj8s7afd"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Questions retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Questions retrieved successfully!"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="exam_id", type="integer", example=1),
     *                     @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *                     @OA\Property(property="option_1", type="string", example="Kathmandu"),
     *                     @OA\Property(property="option_value_1", type="boolean", example=true),
     *                     @OA\Property(property="option_2", type="string", example="Pokhara"),
     *                     @OA\Property(property="option_value_2", type="boolean", example=false),
     *                     @OA\Property(property="option_3", type="string", nullable=true, example="Lalitpur"),
     *                     @OA\Property(property="option_value_3", type="boolean", nullable=true, example=true),
     *                     @OA\Property(property="option_4", type="string", nullable=true, example="Bhaktapur"),
     *                     @OA\Property(property="option_value_4", type="boolean", nullable=true, example=false),
     *                     @OA\Property(property="explanation", type="string", nullable=true, example="Kathmandu is the capital of Nepal."),
     *                     @OA\Property(property="subject", type="string", nullable=true, example="Geography"),
     *                     @OA\Property(property="exam_type", type="string", nullable=true, example="MCQ"),
     *                     @OA\Property(property="remark", type="string", nullable=true, example="Important question"),
     *                     @OA\Property(property="serial", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="old_exam_id", type="integer", nullable=true, example=123),
     *                     @OA\Property(property="mark_type", type="string", nullable=true, example="Numerical")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No active questions found for the specified exam ID",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No active questions found for this exam.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve questions. Please try again."),
     *             @OA\Property(property="error", type="string", example="Database error or other internal error")
     *         )
     *     )
     * )
     */
    public function mockTestQuestions(Exam $exam_id)
    {
        $exam = $exam_id;
        if ($exam->status != ExamTypeEnum::MOCK_TEST->value || $exam->exam_type_id != Auth::guard('api')->user()->exam_type_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Exam Id for Mock Quiz Questions',
            ], 404);
        }

        $first_time_token = null;
        try {
            $first_time_token = $this->checkIfExamHasBeenStartedPreviously($exam, request('token', null));
        } catch (\Exception $e) {
            return Response::apiError($e->getMessage(), null, 409);
        }

        $questionsMockTest = $exam
            ->questions()
            ->with('options')
            ->select('id', 'exam_id', 'question', 'explanation', 'created_at', 'updated_at')
            ->paginate(10);

        $pagination_data = $questionsMockTest->toArray();

        ['links' => $links] = $pagination_data;
        $data               = new QuestionCollection($questionsMockTest);

        $links['current_page'] = $questionsMockTest->currentPage();
        $links['last_page']    = $questionsMockTest->lastPage();
        $links['total']        = $questionsMockTest->total();
        $token                 = $first_time_token;

        $data = compact('data', 'links', 'token');

        return Response::apiSuccess('Questions retrieved successfully!', $data);
    }

    /**
     * @OA\Get(
     *     path="/sprint-quiz/questions/{exam_id}",
     *     summary="Get all active questions for a specific exam",
     *     description="This endpoint retrieves all active questions (status = 3) for a given exam by its ID.",
     *     operationId="sprintQuizQuestions",
     *     tags={"Quiz"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="exam_id",
     *         in="path",
     *         required=true,
     *         description="ID of the exam to retrieve questions for",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="query",
     *         required=false,
     *         description="pagination token",
     *         @OA\Schema(
     *             type="string",
     *             example="kj8s7afd"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Questions retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Questions retrieved successfully!"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="exam_id", type="integer", example=1),
     *                     @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *                     @OA\Property(property="option_1", type="string", example="Kathmandu"),
     *                     @OA\Property(property="option_value_1", type="boolean", example=true),
     *                     @OA\Property(property="option_2", type="string", example="Pokhara"),
     *                     @OA\Property(property="option_value_2", type="boolean", example=false),
     *                     @OA\Property(property="option_3", type="string", nullable=true, example="Lalitpur"),
     *                     @OA\Property(property="option_value_3", type="boolean", nullable=true, example=true),
     *                     @OA\Property(property="option_4", type="string", nullable=true, example="Bhaktapur"),
     *                     @OA\Property(property="option_value_4", type="boolean", nullable=true, example=false),
     *                     @OA\Property(property="explanation", type="string", nullable=true, example="Kathmandu is the capital of Nepal."),
     *                     @OA\Property(property="subject", type="string", nullable=true, example="Geography"),
     *                     @OA\Property(property="exam_type", type="string", nullable=true, example="MCQ"),
     *                     @OA\Property(property="remark", type="string", nullable=true, example="Important question"),
     *                     @OA\Property(property="serial", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="old_exam_id", type="integer", nullable=true, example=123),
     *                     @OA\Property(property="mark_type", type="string", nullable=true, example="Numerical")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No active questions found for the specified exam ID",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No active questions found for this exam.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve questions. Please try again."),
     *             @OA\Property(property="error", type="string", example="Database error or other internal error")
     *         )
     *     )
     * )
     */
    public function sprintQuizQuestions(Exam $exam_id)
    {
        $exam = $exam_id;
        if ($exam->status != ExamTypeEnum::SPRINT_QUIZ->value || $exam->exam_type_id != Auth::guard('api')->user()->exam_type_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Exam Id for Sprint Quiz Questions',
            ], 404);
        }

        $first_time_token = null;
        try {
            $first_time_token = $this->checkIfExamHasBeenStartedPreviously($exam, request('token', null));
        } catch (\Exception $e) {
            return Response::apiError($e->getMessage(), null, 409);
        }

        $questionsSprintQuiz = $exam->questions()
            ->with('options')
            ->select('id', 'exam_id', 'question', 'explanation', 'created_at', 'updated_at')
            ->paginate(10);

        $pagination_data = $questionsSprintQuiz->toArray();

        ['links' => $links] = $pagination_data;
        $data               = new QuestionCollection($questionsSprintQuiz);

        $links['current_page'] = $questionsSprintQuiz->currentPage();
        $links['last_page']    = $questionsSprintQuiz->lastPage();
        $links['total']        = $questionsSprintQuiz->total();
        $token                 = $first_time_token;

        $data = compact('data', 'links', 'token');

        return Response::apiSuccess('Questions retrieved successfully!', $data);
    }

    private function checkIfExamHasBeenStartedPreviously(Exam $exam, $FTT): String
    {
        $user      = Auth::guard('api')->user();
        $user_exam = $user->student_exams()->firstWhere('exam_id', $exam->id);

        if ($user_exam == null) {
            # this is the first time user giving this exam
            $first_time_token = str()->random(25);
            $data             = $exam->questions->pluck('id')->map(fn($id) => ['question_id' => $id]);
            $user->student_exams()->create(['exam_id' => $exam->id, 'first_time_token' => $first_time_token])
                ->answers()
                ->createMany($data);
            return $first_time_token;
        } else {
            # check token to verify if this is first time user attending this exam via token
            if ($user_exam->first_time_token !== $FTT) {
                throw new \Exception('This exam has already been completed by the user.');
            }
            return $FTT;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/questions/{id}",
     *     summary="Delete a question",
     *     description="Delete a question by its ID.",
     *     operationId="deleteExamQuestion",
     *     tags={"MCQs"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The ID of the question to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Question deleted successfully!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Question not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to delete the question",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete the question. Please try again.")
     *         )
     *     )
     * )
     */
    public function destroy(string $id)
    {
        try {
            // Find the question by ID
            $question = Question::findOrFail($id);

            // Delete the question
            $question->delete();

            // Return a success response
            return response()->json([
                'success' => true,
                'message' => 'Question deleted successfully!',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete the question. Please try again.',
            ], 500);
        }
    }
}
