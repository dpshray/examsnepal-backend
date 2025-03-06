<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentProfile;
use App\Models\Exam;
use App\Models\ExamType;

class QuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    /**
     * @OA\Get(
     *     path="/questions",
     *     operationId="getQuestions",
     *     tags={"Questions"},
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
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Fetch student's profile
        $studentProfile = StudentProfile::find($user->id);
        if (!$studentProfile) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }
        $userExamType = $studentProfile->exam_type;
        $examType = ExamType::where('name', $userExamType)->first();
        if (!$examType) {
            return response()->json([
                'success' => false,
                'message' => 'Exam type not found in the system.'
            ], 404);
        }
        $examIds = Exam::where('exam_type_id', $examType->id)->pluck('id');
        if ($examIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No exams found for this exam type.'
            ], 404);
        }
        // Get all questions that belong to these exams
        $questions = Question::whereIn('exam_id', $examIds)->paginate(25);;
        return response()->json([
            'success' => true,
            'message' => 'Questions retrieved successfully!',
            'data' => $questions
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/questions/all",
     *     operationId="getAllQuestions",
     *     tags={"Questions"},
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
        $questions = Question::all();
        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No questions found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Questions retrieved successfully!',
            'data' => $questions,
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
     *     summary="Create a new question",
     *     description="This endpoint allows users to create a new question.",
     *     operationId="storeQuestion",
     *     tags={"Questions"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"exam_id", "question", "option_1", "option_value_1", "option_2", "option_value_2"},
     *                 @OA\Property(property="exam_id", type="integer", example=1),
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
            'exam_id' => 'required|exists:exams,id',
            'question' => 'required|string|max:255',
            'option_1' => 'required|string|max:255',
            'option_value_1' => 'required|boolean',
            'option_2' => 'required|string|max:255',
            'option_value_2' => 'required|boolean',
            'option_3' => 'nullable|string|max:255',
            'option_value_3' => 'nullable|boolean',
            'option_4' => 'nullable|string|max:255',
            'option_value_4' => 'nullable|boolean',
            'explanation' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'exam_type' => 'nullable|string|max:255',
            'remark' => 'nullable|string|max:255',
            'serial' => 'nullable|integer',
            'old_exam_id' => 'nullable|integer',
            'uploader' => 'nullable|exists:users,id',
            'mark_type' => 'nullable|string|max:255',
        ]);

        try {
            // Create a new Question record
            $question = Question::create($validatedData);

            // Return a success response
            return response()->json([
                'success' => true,
                'message' => 'Question created successfully!',
                'data' => $question, // The `data` field is directly included here
            ], 201); // HTTP 201 Created
        } catch (\Exception $e) {
            // Return a failure response if there's an error
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Question. Please try again.',
                'error' => $e->getMessage(),
            ], 500); // HTTP 500 Internal Server Error
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
     *     tags={"Questions"},
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
                'data' => $question,
            ], 200); // HTTP 200 OK
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Return a failure response if the question is not found
            return response()->json([
                'success' => false,
                'message' => 'Question not found',
                'error' => $e->getMessage(),
            ], 404); // HTTP 404 Not Found
        } catch (\Exception $e) {
            // Return a failure response for other errors
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve the question. Please try again.',
                'error' => $e->getMessage(),
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
     *     tags={"Questions"},
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
                'exam_id' => 'required|exists:exams,id',
                'question' => 'required|string|max:255',
                'option_1' => 'required|string|max:255',
                'option_value_1' => 'required|boolean',
                'option_2' => 'required|string|max:255',
                'option_value_2' => 'required|boolean',
                'option_3' => 'nullable|string|max:255',
                'option_value_3' => 'nullable|boolean',
                'option_4' => 'nullable|string|max:255',
                'option_value_4' => 'nullable|boolean',
                'explanation' => 'nullable|string',
                'subject' => 'nullable|string|max:255',
                'exam_type' => 'nullable|string|max:255',
                'remark' => 'nullable|string|max:255',
                'serial' => 'nullable|integer',
                'old_exam_id' => 'nullable|integer',
                'uploader' => 'nullable|exists:users,id',
                'mark_type' => 'nullable|string|max:255',
            ]);

            // Find the question by ID
            $question = Question::findOrFail($id);

            // Update the question with validated data
            $question->update($validatedData);

            // Return a success response
            return response()->json([
                'success' => true,
                'message' => 'Question updated successfully!',
                'data' => $question,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update the question. Please try again.',
            ], 500);
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
     *     tags={"Questions"},
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
