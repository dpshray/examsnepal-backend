<?php

namespace App\Http\Controllers;

use App\Enums\ExamTypeEnum;
use App\Http\Resources\PlayerExamScoreCollection;
use Illuminate\Http\Request;
use App\Models\Exam;
use App\Traits\PaginatorTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    use PaginatorTrait;
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/exam",
     *     summary="Get all exams",
     *     description="Fetches a list of exams along with their associated organizations and exam types.",
     *     operationId="getExams",
     *     tags={"Exams"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of exams retrieved successfully",
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
    public function index()
    {
        //
        $exams = Exam::with(['organization', 'examType'])->paginate();
        return response()->json($exams);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }



    /**
     * @OA\Post(
     *     path="/exam",
     *     summary="Create a new exam",
     *     description="Store a new exam in the database.",
     *     operationId="storeExam",
     *     tags={"Exams"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Exam data to be stored",
     *         @OA\JsonContent(
     *             required={"organization_id", "exam_type_id", "name", "exam_date", "exam_time"},
     *             @OA\Property(property="organization_id", type="integer", example=1),
     *             @OA\Property(property="exam_type_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Mathematics Final Exam"),
     *             @OA\Property(property="description", type="string", example="A detailed mathematics final exam."),
     *             @OA\Property(property="exam_date", type="string", format="date", example="2025-06-15"),
     *             @OA\Property(property="exam_time", type="string", format="time", example="09:00:00"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="price", type="integer", example=500),
     *             @OA\Property(property="test_type", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Exam created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Exam created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="organization_id", type="integer", example=1),
     *                 @OA\Property(property="exam_type_id", type="integer", example=1),
     *                 @OA\Property(property="exam_name", type="string", example="Mathematics Final Exam"),
     *                 @OA\Property(property="description", type="string", example="A detailed mathematics final exam."),
     *                 @OA\Property(property="exam_date", type="string", format="date", example="2025-06-15"),
     *                 @OA\Property(property="exam_time", type="string", format="time", example="09:00:00"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="price", type="integer", example=500),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-01T12:00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input data",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Validation failed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to store exam")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        // Validate the request data
        $validated = $request->validate([
            'exam_type_id' => 'required|integer|exists:exam_types,id',
            'exam_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exam_date' => 'required|string',
            'exam_time' => 'required|string',
            'is_active' => 'nullable|boolean',
            'price' => 'nullable|integer',
            'test_type' => ['required', Rule::enum(ExamTypeEnum::class)]
        ]);

        try {
            // Create the exam
            $validated['status'] = $validated['test_type'];
            $exam = Exam::create($validated);

            // Return a successful response with the created exam
            return response()->json([
                'message' => 'Exam created successfully',
                'data' => $exam
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to store exam',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/exam/{id}",
     *     summary="Get an exam by ID",
     *     description="Retrieve the details of a specific exam by its ID.",
     *     operationId="getExamById",
     *     tags={"Exams"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the exam to retrieve",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam details",
     *         @OA\JsonContent(
     *             type="object",
     *             properties={
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="exam_date", type="string", format="date"),
     *                 @OA\Property(property="exam_time", type="string", format="time"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="price", type="integer"),
     *                 @OA\Property(property="organization", type="object", 
     *                     properties={
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string")
     *                     }
     *                 ),
     *                 @OA\Property(property="exam_type", type="object", 
     *                     properties={
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string")
     *                     }
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Exam not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Exam not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve exam")
     *         )
     *     )
     * )
     */
    public function show($id): JsonResponse
    {
        // Find the exam by ID
        $exam = Exam::with(['organization', 'examType'])->find($id);

        // Check if the exam exists
        if (!$exam) {
            return response()->json(['error' => 'Exam not found'], 404);
        }

        // Return the exam details as JSON
        return response()->json($exam);
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
     *     path="/exam/{id}",
     *     summary="Update an existing exam",
     *     description="Update an exam's details in the database.",
     *     operationId="updateExam",
     *     tags={"Exams"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the exam to be updated",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Exam data to be updated",
     *         @OA\JsonContent(
     *             required={"organization_id", "exam_type_id", "name", "exam_date", "exam_time"},
     *             @OA\Property(property="organization_id", type="integer", example=1),
     *             @OA\Property(property="exam_type_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Updated Mathematics Final Exam"),
     *             @OA\Property(property="description", type="string", example="Updated description of the mathematics final exam."),
     *             @OA\Property(property="exam_date", type="string", format="date", example="2025-07-15"),
     *             @OA\Property(property="exam_time", type="string", format="time", example="10:00:00"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="price", type="integer", example=600)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Exam updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="organization_id", type="integer", example=1),
     *                 @OA\Property(property="exam_type_id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Updated Mathematics Final Exam"),
     *                 @OA\Property(property="description", type="string", example="Updated description of the mathematics final exam."),
     *                 @OA\Property(property="exam_date", type="string", format="date", example="2025-07-15"),
     *                 @OA\Property(property="exam_time", type="string", format="time", example="10:00:00"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="price", type="integer", example=600),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-07-01T12:00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input data",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Validation failed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Exam not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Exam not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to update exam")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        // Find the exam by ID
        $exam = Exam::find($id);

        // Check if the exam exists
        if (!$exam) {
            return response()->json(['error' => 'Exam not found'], 404);
        }

        // Validate the request data
        $validated = $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'exam_type_id' => 'required|integer|exists:exam_types,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exam_date' => 'required|date',
            'exam_time' => 'required|time',
            'is_active' => 'nullable|boolean',
            'price' => 'nullable|integer',
        ]);

        try {
            // Update the exam
            $exam->update($validated);

            // Return a successful response with the updated exam
            return response()->json([
                'message' => 'Exam updated successfully',
                'data' => $exam
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update exam',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */


    /**
     * @OA\Delete(
     *     path="/exam/{id}",
     *     summary="Delete an exam by ID",
     *     description="Delete an exam from the database by its ID.",
     *     operationId="deleteExam",
     *     tags={"Exams"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the exam to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Exam deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Exam not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Exam not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to delete exam")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $exam = Exam::find($id);

        if (!$exam) {
            return response()->json(['error' => 'Exam not found'], 404);
        }

        try {
            $exam->delete();

            return response()->json(['message' => 'Exam deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete exam',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/exam-scorers/{exam}",
     *     summary="Get all exams scorers",
     *     description="Fetches all exams scorers.",
     *     operationId="GetExamScorers",
     *     tags={"Exams"},
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="ID of the exam",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page no of player list",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of players per response",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam with its lists of players with scores",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="exam with its lists of players with scores"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2127),
     *                 @OA\Property(property="exam_name", type="string", example="Physiology Quiz on Blood Pressure and Regulation"),
     *                 @OA\Property(property="status", type="string", example="free"),
     *                 @OA\Property(
     *                     property="players",
     *                     type="object",
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=7570),
     *                             @OA\Property(property="name", type="string", example="Saroj sah"),
     *                             @OA\Property(
     *                                 property="solutions",
     *                                 type="object",
     *                                 @OA\Property(property="marks", type="number", format="float", example=23),
     *                                 @OA\Property(property="full_marks", type="integer", example=30),
     *                                 @OA\Property(property="correct_answer_count", type="integer", example=23),
     *                                 @OA\Property(property="is_negative_marking", type="boolean", example=true),
     *                                 @OA\Property(property="negative_marking_point", type="number", format="float", example=0.25),
     *                                 @OA\Property(property="incorrect_answer_count", type="integer", example=0),
     *                                 @OA\Property(property="missed_answer_count", type="integer", example=7),
     *                                 @OA\Property(property="total_point_reduction_based_on_negative_marking_point", type="number", format="float", example=0)
     *                             )
     *                         )
     *                     ),
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=7),
     *                     @OA\Property(property="total", type="integer", example=104)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function examPlayersScoreList(Request $request, Exam $exam)
    {
        $per_page = $request->query('per_page', 10);
        // $per_page = $per_page ? $per_page : ;
        $status = null;
        if (!empty($exam->status)) {
            $raw = ExamTypeEnum::getKeyByValue($exam->status);
            $status = explode('_', strtolower($raw))[0];
        }

        $student_exams = $exam->student_exams()
            ->select([
                'student_exams.id',
                'student_exams.student_id',
                'student_exams.exam_id',
                'student_exams.created_at',
            ])
            ->with([
                'student:id,name',
                'exam.questions'
            ])
            ->withCount([
                'correct_answers as correct_answer_count',
                'incorrect_answers as incorrect_answer_count'
            ])
            ->selectRaw('
                (
                    (select count(*) from answersheets 
                    where answersheets.student_exam_id = student_exams.id 
                    and is_correct = 1)
                    -
                    (
                        (select count(*) from answersheets 
                        where answersheets.student_exam_id = student_exams.id 
                        and is_correct = 0) * ?
                    )
                ) as marks
            ', [$exam->negative_marking_point])
            ->orderByDesc('marks')
            ->orderByDesc('correct_answer_count')
            ->paginate($per_page);

        /* $student_exams = $exam->student_exams()
            ->select(['id', 'student_id', 'exam_id','created_at'])
            ->with([
                'student:id,name',
                'exam.questions'
            ])
            ->withCount([
                'answers as correct_answer_count' => fn($q) => $q->where('is_correct', 1),
                'answers as incorrect_answer_count' => fn($q) => $q->where('is_correct', 0),
                'answers as missed_answer_count' => fn($q) => $q->where('is_correct', null),
            ])
            ->orderBy('correct_answer_count', 'DESC')
            ->orderBy('id', 'DESC')
            ->paginate($per_page); */

        // $data = new \App\Http\Resources\ExamResource($exam);
        $players = $this->setupPagination($student_exams, PlayerExamScoreCollection::class)->data;
        $data = [
            "id" => $exam->id,
            "exam_name" => $exam->exam_name,
            "status" =>  $status,
            // "user" => $exam->user, #<---added_by
            'players' => $players
        ];
        return Response::apiSuccess('exam with its lists of players with scores', $data);
    }
}
