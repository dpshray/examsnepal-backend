<?php

namespace App\Http\Controllers;

use App\Http\Resources\DoubtCollection;
use Illuminate\Http\Request;
use App\Models\Doubt;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Traits\PaginatorTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class DoubtController extends Controller
{
    use PaginatorTrait;
    /**
     * @OA\Get(
     *     path="/doubt/student/solved",
     *     summary="Get logged in student question solved doubts",
     *     tags={"Doubts"},
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

    public function fetchAuthStudentDoubtSolved()
    {
       $user_doubt = Auth::guard('api')
                        ->user()
                        ->doubts()
                        ->where('status',0)
                        ->with(['question','solver:id,username,fullname'])
                        ->paginate();
        $data = $this->setupPagination($user_doubt, DoubtCollection::class)->data;
        return Response::apiSuccess('User doubt retrieved successfully!', $data);
    }    
    
    /**
     * @OA\Get(
     *     path="/doubt/student/unsolved",
     *     summary="Get logged in student question unsolved doubts",
     *     tags={"Doubts"},
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

    public function fetchAuthStudentDoubtUnsolved()
    {
       $user_doubt = Auth::guard('api')
                        ->user()
                        ->doubts()
                        ->where('status',1)
                        ->with(['question.options','solver:id,username,fullname'])
                        ->paginate();
        $data = $this->setupPagination($user_doubt, DoubtCollection::class)->data;

        return Response::apiSuccess('User doubt retrieved successfully!', $data);
    }
    
    /**
     * Display a listing of user own .
     */
    /**
     * @OA\Get(
     *     path="/doubts",
     *     summary="Get all doubts",
     *     tags={"Doubts"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Fetched all doubts successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Fetched all doubts successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function index()
    {
        //
        $doubts = Doubt::select('id', 'doubt', 'created_at', 'updated_at', 'status', 'exam_id', 'student_id', 'org_id', 'question_id')
                    ->where('status', '1')
                    ->with('exam:id,exam_name', 'student:id,name', 'organization:id,fullname', 'question:id,question')
                    ->paginate();

        return response()->json([
            'message' => 'Fetched all doubts successfully.',
            'data' => $doubts,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    /**
     * @OA\Post(
     *     path="/doubt",
     *     summary="Create a new doubt(Student)",
     *     description="Creates a new doubt in the system.",
     *     tags={"Doubts"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"doubt", "exam_id", "org_id", "question_id"},
     *             @OA\Property(property="doubt", type="string", example="This is a sample doubt."),
     *             @OA\Property(property="question_id", type="integer", example=101),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Doubt successfully created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Doubt successfully created"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="doubt", type="string", example="This is a sample doubt."),
     *                 @OA\Property(property="exam_id", type="integer", example=1),
     *                 @OA\Property(property="org_id", type="integer", example=1),
     *                 @OA\Property(property="question_id", type="integer", example=101),
     *                 @OA\Property(property="status", type="string", example="1"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-25T00:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-25T00:00:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="doubt", type="array", @OA\Items(type="string", example="The doubt field is required.")),
     *                 @OA\Property(property="exam_id", type="array", @OA\Items(type="string", example="The exam id must exist in the exams table.")),
     *                 @OA\Property(property="org_id", type="array", @OA\Items(type="string", example="The org id must exist in the organizations table.")),
     *                 @OA\Property(property="question_id", type="array", @OA\Items(type="string", example="The question id must exist in the questions table."))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="An unexpected error occurred",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred"),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $student = Auth::guard('api')->user();
        $validatedData = $request->validate([
            'doubt' => 'required|string',
            'question_id' => 'required|exists:questions,id',
        ]);
        $doubt = $student->doubts();
        if ($doubt->where('status',1)->firstWhere('question_id', $validatedData['question_id'])) {
            return Response::apiError('This question doubt has already been added',null,412);
        }

        $organization_id = Question::findOrFail($validatedData['question_id'])->exam->user_id;
        $validatedData['organization_id'] = $organization_id; 
        $data = $doubt->create($validatedData);
        return Response::apiSuccess('Doubt posted',$data);
    }


    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/doubt/{id}",
     *     summary="Get a specific doubt",
     *     tags={"Doubts"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Doubt ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Fetched resource successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Fetched resource successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function show(string $id)
    {
        //
        return response()->json([
            'message' => "Fetched resource with ID $id successfully.",
            'data' => ['id' => $id, 'name' => 'Dummy Resource']
        ], 200);
    }
}
