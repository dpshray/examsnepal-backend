<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ForumQuestion;
use App\Models\ForumAnswer;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;


/**
 * @OA\Info(
 *     title="ExamsNepal  API",
 *     version="1.0.0",
 *     description="API Endpoints for Forum Questions"
 * )
 * 
 * @OA\Server( 
 *  url="https://api.examsnepal.dworklabs.com/api",
 *     description="Localhost API Server"
 * )
 * 
 * @OA\Tag(
 *     name="Forum",
 *     description="API Endpoints for Managing Forum Questions"
 * )
 */

class ForumController extends Controller
{

    // Private method to check authentication and fetch student profile
    private function getAuthenticatedStudentProfile()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $studentProfile = StudentProfile::find($user->id);

        if (!$studentProfile) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        return $studentProfile;
    }

    /**
     * @OA\Get(
     *     path="/student/questions",
     *     tags={"Forum"},
     *     summary="Fetch all questions based on the user's stream",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of questions"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */

    public function fetchQuestions()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Fetch the student profile using the logged-in user's id
        $studentProfile = StudentProfile::find($user->id);

        if (!$studentProfile) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        // Fetch questions where the stream matches the student's exam_type
        $questions = ForumQuestion::where('stream', $studentProfile->exam_type)
            ->where('forum_questions.deleted', '0') // Only fetch non-deleted questions
            ->with(['studentProfile:id,name,email', 'answers.studentProfile:id,name,email'])
            ->withCount('answers')
            ->get();

        return response()->json($questions);
    }

    /**
     * @OA\Get(
     *     path="/student/myquestions",
     *     tags={"Forum"},
     *     summary="Fetch all questions created by me",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of questions created by me"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */

    public function fetchMyQuestions()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Fetch the student profile using the logged-in user's id
        $studentProfile = StudentProfile::find($user->id);

        if (!$studentProfile) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        // Fetch questions where the stream matches the student's exam_type
        $questions = ForumQuestion::where('stream', $studentProfile->exam_type)
            ->where('forum_questions.deleted', '0') // Only fetch non-deleted questions
            ->where('user_id', $studentProfile->id)
            ->where('forum_questions.deleted', '0') // Only fetch non-deleted questions
            ->with(['studentProfile:id,name,email', 'answers.studentProfile:id,name,email'])
            ->withCount('answers')
            ->get();

        return response()->json($questions);
    }
    // Method to fetch questions by substream
    public function fetchQuestionsBySubstream($subStream)
    {
        $studentProfile = $this->getAuthenticatedStudentProfile();

        if ($studentProfile instanceof \Illuminate\Http\JsonResponse) {
            return $studentProfile; // Return the unauthorized or not found response
        }
        // Fetch questions where the substream matches the provided substream
        $questions = ForumQuestion::where('stream', $studentProfile->exam_type) // Filter by exam_type
            ->where('substream', $subStream) // Filter by substream
            ->where('forum_questions.deleted', '0') // Only fetch non-deleted questions
            ->with(['studentProfile:id,name,email', 'answers.studentProfile:id,name,email'])
            ->withCount('answers')
            ->get();
        return response()->json($questions);
    }

    /**
     * @OA\Post(
     *     path="/student/addquestion",
     *     summary="Add a new question",
     *     description="Adds a new question to the forum for the authenticated user.",
     *     operationId="addQuestion",
     *     tags={"Forum"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"question"},
     *             @OA\Property(
     *                 property="question",
     *                 type="string",
     *                 description="The question to be added.",
     *                 example="What is the best way to improve my coding skills?"
     *             ),
     *      
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Question added successfully.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Question added successfully"),
     *             @OA\Property(property="question", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=123),
     *                 @OA\Property(property="question", type="string", example="What is the best way to improve my coding skills?"),
     *                 @OA\Property(property="stream", type="string", example="Computer Science"),
     *                 @OA\Property(property="deleted", type="integer", example=0)
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
     *         description="Student profile not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Student profile not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="question", type="array", @OA\Items(type="string", example="The question field is required"))
     *             )
     *         )
     *     )
     * )
     */



    // Method to add a question
    public function addQuestion(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Fetch the student profile using the logged-in user's id
        $studentProfile = StudentProfile::find($user->id);

        if (!$studentProfile) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create the question
        $question = ForumQuestion::create([
            'user_id' => $studentProfile->id, // Use the id from student_profiles
            'question' => $request->input('question'),
            'stream' => $studentProfile->exam_type, // Use the exam_type from student_profiles as the stream
            'deleted' => '0', // Ensure deleted_at is null for new questions
        ]);

        return response()->json([
            'message' => 'Question added successfully',
            'question' => $question,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/student/questions/edit/{id}",
     *     summary="Update a question by its ID if only created Question by Authenticated user",
     *     description="Update a question created by the authenticated user. Performs validation for uniqueness, stream type, and updates the question details.",
     *     operationId="updateQuestion",
     *     tags={"Forum"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The ID of the question to be updated",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Request body to update the question",
     *         @OA\JsonContent(
     *             required={"question"},
     *             @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Question updated successfully"),
     *             @OA\Property(property="question", type="object", 
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *                 @OA\Property(property="stream", type="string", example="Science"),
     *                 @OA\Property(property="deleted", type="integer", example=0),
     *             )
     *         ),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Stream type does not match for the student",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Stream type does not match for the student")
     *         ),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question not found or already deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Question not found or already deleted")
     *         ),
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors in the request",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object", 
     *                 @OA\Property(property="question", type="array", 
     *                     @OA\Items(type="string", example="The question field is required.")
     *                 )
     *             )
     *         ),
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="The user has already created this question",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You have already created this question")
     *         ),
     *     ),
     * )
     */
    public function updateQuestion(Request $request, $id)
    {
        $studentProfile = $this->getAuthenticatedStudentProfile();

        if ($studentProfile instanceof \Illuminate\Http\JsonResponse) {
            return $studentProfile; // Return the unauthorized or not found response
        }

        // Find the question that the user wants to update
        $question = ForumQuestion::where('id', $id)
            ->where('user_id', $studentProfile->id)
            ->where('deleted', '0')
            ->first();

        if (!$question) {
            return response()->json(['message' => 'Question not found or already deleted'], 404);
        }

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if the updated question already exists for the user
        $existingQuestion = ForumQuestion::where('user_id', $studentProfile->id)
            ->where('question', $request->input('question'))
            ->where('id', '!=', $id)
            ->where('deleted', '0')
            ->first();

        if ($existingQuestion) {
            return response()->json(['message' => 'You have already created this question'], 409);
        }

        // Check if the student profile has a stream type
        if (strcmp((string) $studentProfile->exam_type, (string) $question->stream) !== 0) {
            return response()->json(['message' => 'Stream type does not match for the student'], 400);
        }

        // // Update the question
        $question->update([
            'question' => $request->input('question'),
        ]);

        return response()->json([
            'message' => 'Question updated successfully',
            'question' => $question,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/student/questions/their/{id}",
     *     summary="Get a question by its ID Created By authenticated user",
     *     description="Fetch the details of a question if the authenticated user is the owner. Unauthorized access will return a 403 response.",
     *     operationId="getQuestionByTheirId",
     *     tags={"Forum"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The ID of the question to be fetched",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question details fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Question details"),
     *             @OA\Property(property="question", type="object", 
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *                 @OA\Property(property="stream", type="string", example="Science"),
     *                 @OA\Property(property="deleted", type="integer", example=0),
     *             )
     *         ),
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access, the user is not the owner of the question",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are not authorized to access this question")
     *         ),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Question not found")
     *         ),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID format",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid ID format")
     *         ),
     *     ),
     * )
     */

    public function getQuestionByTheirId($id): JsonResponse
    {
        $user = Auth::user();

        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid ID format'], 400);
        }

        $question = ForumQuestion::find($id);
        // Check if the question exists
        if ($question) {
            // Check if the authenticated user is the owner
            if ($question->user_id == $user->id) {
                return response()->json([
                    'message' => 'Question details',
                    'question' => $question
                ], 200);
            } else {
                return response()->json([
                    'message' => 'You are not authorized to access this question'
                ], 403);
            }
        } else {
            return response()->json([
                'message' => 'Question not found'
            ], 404);
        }
    }
    /**
     * @OA\Get(
     *     path="/student/questions/{id}",
     *     summary="Get a question by its ID",
     *     description="Fetch the details of a specific question by its ID. Only the owner of the question can view the details.",
     *     operationId="getQuestionById",
     *     tags={"Forum"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The ID of the question",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question details fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Question details"),
     *             @OA\Property(property="question", type="object", 
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *                 @OA\Property(property="stream", type="string", example="Science"),
     *                 @OA\Property(property="deleted", type="integer", example=0),
     *             )
     *         ),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID format",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid ID format")
     *         ),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Question not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Question not found")
     *         ),
     *     ),
     * )
     */
    public function getQuestionById($id): JsonResponse
    {
        $user = Auth::user();

        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid ID format'], 400);
        }

        $question = ForumQuestion::find($id);
        // Check if the question exists
        if ($question) {
            // Check if the authenticated user is the owner
            return response()->json([
                'message' => 'Question details',
                'question' => $question
            ], 200);
        } else {
            return response()->json([
                'message' => 'Question not found'
            ], 404);
        }
    }
    /**
     * @OA\Delete(
     *     path="/student/questions/{id}",
     *     summary="Delete a question by its ID",
     *     description="Deletes a question if the authenticated user is the owner of the question.",
     *     operationId="deleteTheirQuestionCreated",
     *     tags={"Forum"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The ID of the question to be deleted",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Question deleted successfully")
     *         ),
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized or Question not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized or Question not found")
     *         ),
     *     ),
     * )
     */
    public function deleteTheirQuestionCreated($id)
    {
        // Get the currently authenticated user
        $user = Auth::user();
        // Find the question by its ID
        $question = ForumQuestion::find($id);
        // Check if the question exists and if the authenticated user is the owner
        if ($question && $question->user_id == $user->id) {
            // Delete the question
            $question->deleted = 1;
            $question->save();
            return response()->json(['message' => 'Question deleted successfully']);
        } else {
            return response()->json(['message' => 'Unauthorized or Question not found'], 403);
        }
    }

    // forum answers
    /**
     * @OA\Post(
     *     path="/student/answers",
     *     summary="Add an answer to a question",
     *     description="Allows authenticated students to add an answer to a specified question in the forum.",
     *     operationId="addAnswer",
     *     tags={"Forum"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"question_id", "answer"},
     *             @OA\Property(
     *                 property="question_id",
     *                 type="integer",
     *                 description="ID of the question being answered.",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="answer",
     *                 type="string",
     *                 description="The answer to the question.",
     *                 example="To improve your coding skills, practice daily and engage with community projects."
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Answer added successfully.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Answer added successfully!"),
     *             @OA\Property(property="answer", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="question_id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=123),
     *                 @OA\Property(property="answer", type="string", example="To improve your coding skills, practice daily and engage with community projects."),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-21T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-21T12:00:00Z")
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
     *         description="Question not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Question not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="question_id", type="array", @OA\Items(type="string", example="The question id field is required")),
     *                 @OA\Property(property="answer", type="array", @OA\Items(type="string", example="The answer field is required"))
     *             )
     *         )
     *     )
     * )
     */
    public function addAnswer(Request $request)
    {
        // Validate the request input
        $validated = $request->validate([
            'question_id' => 'required|exists:forum_questions,id',
            'answer' => 'required|string',
        ]);

        // Get the authenticated user's ID
        $userId = Auth::id();

        try {
            // Create and store the answer
            $answer = ForumAnswer::create([
                'question_id' => $validated['question_id'],
                'user_id' => $userId,
                'answer' => $validated['answer'],
            ]);

            // Return a success response
            return response()->json([
                'message' => 'Answer added successfully!',
                'answer' => $answer,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while adding the answer.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
