<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookmarkCollection;
use App\Models\Bookmark;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Traits\PaginatorTrait;
use Illuminate\Support\Facades\Response;

class BookmarkController extends Controller
{
    use PaginatorTrait;
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/bookmarks",
     *     summary="Get all bookmarks with question and student details",
     *     tags={"Bookmarks"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
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

    public function index()
    {
        // Eager load the related 'ForumQuestion' using the 'question' relationship
        // $bookmarks = Bookmark::with('questions')->get();
        // $uniqueQuestions=Question::with('bookmark')->get();

        // $uniqueQuestions = $bookmarks->pluck('questions.*.id')->flatten()->unique();
        $student_id = Auth::guard('api')->id();
        $questionsWithStudents = Question::select("id","exam_id","exam_type_id","question","explanation")->has('bookmarks')
                                    ->withCount('bookmarks')
                                    ->with(['options','bookmarks.student' => function ($query) {
                                        $query->select('id', 'name'); // Load specific student fields
                                    }])
                                    // ->whereRelation('exam','id',$student_id)
                                    ->paginate(10);
            $data['data'] = $questionsWithStudents->map(function ($question) {
                    // Extract students from bookmarks and add to top-level 'students'
                    $question['students'] = $question->bookmarks->pluck('student')->unique('id')->values();
                    $question['options'] = $question->options;
                    unset($question->bookmarks); // Optional: Remove bookmarks if not needed
                    return $question;
                });
        $data['current_page'] = $questionsWithStudents->currentPage();
        $data['last_page']    = $questionsWithStudents->lastPage();
        $data['total']        = $questionsWithStudents->total();
            return Response::apiSuccess('All Bookmarks', $data);
        // return response()->json($questionsWithStudents);

        // $question = Question::has('bookmarks')->with('options','bookmark.student');
        // return  Question::has('bookmarks')->with(['bookmarks.question.options', 'bookmarks.student'])->get();
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
     *             required={"user_id","question_id"},
     *             @OA\Property(property="student_id", type="integer", example=1),
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
            'question_id' => 'required|exists:questions,id',
        ]);

        // Check if the bookmark already exists
        $student_bookmark = Auth::guard('api')
                                ->user()
                                ->bookmarks();
        if ($student_bookmark->firstWhere('question_id', $validated['question_id'])) {
            return response()->json([
                'error' => 'Duplicate Bookmark',
                'message' => 'This bookmark already exists'
            ], 409);
        }
        $new_student_bookmark = $student_bookmark->create([
            'question_id' => $validated['question_id']
        ]);
        return response()->json([
            'message' => 'Bookmark created successfully',
            'data' => $new_student_bookmark
        ], 201);
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
    public function destroy($question_id)
    {
        $bookmark = Auth::guard('api')->user()->bookmarks()->firstWhere('question_id', $question_id);
        if ($bookmark == null) {
            return Response::apiError('Bookmark does not exists/belongs to another user',null,404);
        }

        $bookmark->delete();
        return Response::apiSuccess('Bookmark removed.');
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
    public function getBookmarksByStudent(StudentProfile $student_id)
    {
        $student = $student_id;
        $student_bookmarks = $student->bookmarks()->with('question.options')->paginate(10);
        $data = $this->setupPagination($student_bookmarks, BookmarkCollection::class)->data;
        $student_name = $student->name;
        return Response::apiSuccess("User {$student_name} bookmark lists", $data);

        // $pagination_data = $student_bookmarks->toArray();
        // ['links' => $links] = $pagination_data;


        // $data = new BookmarkCollection($student_bookmarks);
        // $links['current_page'] = $student_bookmarks->currentPage();
        // $links['last_page']    = $student_bookmarks->lastPage();
        // $links['total']        = $student_bookmarks->total();

        // $data = compact('data', 'links');

        // return Response::apiSuccess('User bookmarks', $data);

        // // Check if the student_id is valid (you can add more validation as needed)
        // if (!is_numeric($student_id) || $student_id <= 0) {
        //     return response()->json(['error' => 'Invalid student ID'], 400);
        // }

        // // Fetch bookmarks for the given student_id
        // $bookmarks = Bookmark::with('questions')
        //     ->where('student_id', $student_id)
        //     ->paginate(10);

        // // Check if any bookmarks exist for the student
        // if ($bookmarks->isEmpty()) {
        //     return response()->json(['error' => 'No bookmarks found for the given student ID'], 404);
        // }
        return response()->json($bookmarks);
    }

    /**
     * @OA\Get(
     *     path="/bookmarks/allmy",
     *     summary="Get all bookmarks for the authenticated user",
     *     description="Fetches all bookmarks for the authenticated user.",
     *     operationId="getAllMyBookmarks",
     *     tags={"Bookmarks"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
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
        $user = Auth::guard('api')->user();
        $user_bookmarks = $user->bookmarks()->with('question.options')->paginate(10);
        $data = $this->setupPagination($user_bookmarks, BookmarkCollection::class)->data;

        return Response::apiSuccess('User bookmarks', $data);
    }
}
