<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BookmarkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/bookmarks",
     *     summary="Get all bookmarks with question and student details",
     *     tags={"Bookmarks"},
     *     @OA\Response(
     *         response=200,
     *         description="List of bookmarks",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="student_id", type="integer", example=101),
     *                 @OA\Property(property="exam_id", type="integer", example=55),
     *                 @OA\Property(property="question_id", type="integer", example=302),
     *                 @OA\Property(property="question", type="object",
     *                     @OA\Property(property="id", type="integer", example=302),
     *                     @OA\Property(property="title", type="string", example="What is Laravel?"),
     *                     @OA\Property(property="content", type="string", example="Laravel is a PHP framework...")
     *                 ),
     *                 @OA\Property(property="student", type="object",
     *                     @OA\Property(property="id", type="integer", example=101),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="johndoe@example.com")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-17T10:00:00.000000Z")
     *             )
     *         )
     *     )
     * )
     */

    public function index(): JsonResponse
    {
        // Eager load the related 'ForumQuestion' using the 'question' relationship
        $bookmarks = Bookmark::with('questions', 'student')->get();
        return response()->json($bookmarks);
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
     *     path="/bookmarks",
     *     summary="Create a new bookmark",
     *     description="Stores a new bookmark.",
     *     operationId="storeBookmark",
     *     tags={"Bookmarks"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "exam_id", "question_id"},
     *             @OA\Property(property="student_id", type="integer", example=1),
     *             @OA\Property(property="exam_id", type="integer", example=1),
     *             @OA\Property(property="question_id", type="integer", example=101)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Bookmark created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Bookmark created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="student_id", type="integer", example=1),
     *                 @OA\Property(property="exam_id", type="integer", example=1),
     *                 @OA\Property(property="question_id", type="integer", example=101)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|integer|exists:student_profiles,id',
            'exam_id' => 'required|integer|exists:exams,id',
            'question_id' => 'required|integer|exists:questions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        if ($validated['student_id'] !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        // Check if the bookmark already exists
        if (Bookmark::where('student_id', $validated['student_id'])
            ->where('question_id', $validated['question_id'])
            ->exists()
        ) {
            return response()->json([
                'error' => 'Duplicate Bookmark',
                'message' => 'This bookmark already exists'
            ], 409);
        }
        try {
            $bookmark = Bookmark::create($validated);
            return response()->json([
                'message' => 'Bookmark created successfully',
                'data' => $bookmark
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create bookmark',
                'message' => $e->getMessage()
            ], 500);
        }
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
    /**
     * @OA\Delete(
     *     path="/bookmarks/{id}",
     *     summary="Delete a bookmark",
     *     operationId="deleteBookmark",
     *     tags={"Bookmarks"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Bookmark deleted successfully"),
     *     @OA\Response(response=404, description="Bookmark not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function destroy($id): JsonResponse
    {
        $bookmark = Bookmark::find($id);
        if (!$bookmark) {
            return response()->json(['error' => 'Bookmark not found'], 404);
        }

        try {
            $bookmark->delete();
            return response()->json(['message' => 'Bookmark deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete bookmark', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/bookmarks/student/{student_id}",
     *     summary="Get bookmarks by student ID",
     *     description="Fetches all bookmarks associated with a given student ID",
     *     tags={"Bookmarks"},
     *     @OA\Parameter(
     *         name="student_id",
     *         in="path",
     *         required=true,
     *         description="ID of the student whose bookmarks are to be retrieved",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of bookmarks",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="student_id", type="integer", example=1),
     *                 @OA\Property(property="exam_id", type="integer", example=5),
     *                 @OA\Property(property="question_id", type="integer", example=10),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-17 10:00:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-17 10:00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid student ID",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid student ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No bookmarks found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No bookmarks found for the given student ID")
     *         )
     *     ),
     *     security={{ "bearerAuth":{} }}
     * )
     */
    public function getBookmarksByStudent($student_id)
    {
        // Check if the student_id is valid (you can add more validation as needed)
        if (!is_numeric($student_id) || $student_id <= 0) {
            return response()->json(['error' => 'Invalid student ID'], 400);
        }

        // Fetch bookmarks for the given student_id
        $bookmarks = Bookmark::with('questions')
            ->where('student_id', $student_id)
            ->get();

        // Check if any bookmarks exist for the student
        if ($bookmarks->isEmpty()) {
            return response()->json(['error' => 'No bookmarks found for the given student ID'], 404);
        }
        return response()->json($bookmarks);
    }

    /**
     * @OA\Get(
     *     path="/bookmarks/allmy",
     *     summary="Get all bookmarks for the authenticated user",
     *     description="Fetches all bookmarks for the authenticated user.",
     *     operationId="getAllMyBookmarks",
     *     tags={"Bookmarks"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Bookmarks retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="student_id", type="integer", example=1),
     *                 @OA\Property(property="question_id", type="integer", example=101),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-04T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-04T12:00:00Z"),
     *                 @OA\Property(property="question", type="object",
     *                     @OA\Property(property="id", type="integer", example=101),
     *                     @OA\Property(property="title", type="string", example="What is Laravel?"),
     *                     @OA\Property(property="body", type="string", example="Can someone explain what Laravel is?")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="User not Authenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No bookmarks found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No bookmarks found for the given student ID")
     *         )
     *     )
     * )
     */
    public function getAllMyBookmarks()
    {
        // Get the authenticated user's ID
        $userId = Auth::id();

        if (!$userId) {
            return response()->json(['error' => 'user not Authenticated'], 401);
        }

        // Fetch bookmarks for the given student_id
        $bookmarks = Bookmark::with('questions')
            ->where('student_id', $userId)
            ->get();

        // Check if any bookmarks exist for the student
        if ($bookmarks->isEmpty()) {
            return response()->json(['error' => 'No bookmarks found for the given student ID'], 404);
        }

        return response()->json($bookmarks);
    }
}
