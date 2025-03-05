<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BookmarkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/bookmarks",
     *     operationId="getAllBookmarks",
     *     tags={"Bookmarks"},
     *     summary="Get all bookmarks with associated forum questions",
     *     description="This endpoint retrieves all bookmarks and includes associated forum questions.",
     *     @OA\Response(
     *         response=200,
     *         description="Bookmarks and their associated forum questions retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", description="The unique ID of the bookmark"),
     *                 @OA\Property(property="student_id", type="integer", description="The ID of the student who created the bookmark"),
     *                 @OA\Property(property="title", type="string", description="The title of the bookmark"),
     *                 @OA\Property(property="url", type="string", description="The URL of the bookmark"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp of the bookmark"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp of the bookmark"),
     *                 @OA\Property(
     *                     property="question",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", description="The unique ID of the forum question"),
     *                     @OA\Property(property="title", type="string", description="The title of the forum question"),
     *                     @OA\Property(property="content", type="string", description="The content of the forum question"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp of the forum question"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp of the forum question")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No bookmarks found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        // Eager load the related 'ForumQuestion' using the 'question' relationship
        $bookmarks = Bookmark::with('question')->get();

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
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:student_profiles,id',
            'exam_id' => 'required|integer|exists:exams,id',
            'question_id' => 'required|integer|exists:forum_questions,id',  // Added question_id validation
        ]);

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
     *     path="/bookmarks/student/{id}",
     *     operationId="getBookmarksByStudent",
     *     tags={"Bookmarks"},
     *     summary="Get bookmarks by student ID",
     *     description="This endpoint retrieves all bookmarks for a specific student based on their student ID.",
     *     @OA\Parameter(
     *         name="student_id",
     *         in="path",
     *         required=true,
     *         description="ID of the student to get the bookmarks for",
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bookmarks retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="student_id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No bookmarks found for the given student ID"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid student ID provided"
     *     )
     * )
     */
    public function getBookmarksByStudent($id)
    {
        // Check if the student_id is valid (you can add more validation as needed)
        if (!is_numeric($id) || $id <= 0) {
            return response()->json(['error' => 'Invalid student ID'], 400);
        }

        // Fetch bookmarks for the given student_id
        $bookmarks = Bookmark::with('student')
            ->where('student_id', $id)
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
        $bookmarks = Bookmark::with('student')
            ->where('student_id', $userId)
            ->get();

        // Check if any bookmarks exist for the student
        if ($bookmarks->isEmpty()) {
            return response()->json(['error' => 'No bookmarks found for the given student ID'], 404);
        }

        return response()->json($bookmarks);
    }
}
