<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class ExamController extends Controller
{
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
     *             @OA\Property(property="price", type="integer", example=500)
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
     *                 @OA\Property(property="name", type="string", example="Mathematics Final Exam"),
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exam_date' => 'required|string',
            'exam_time' => 'required|string',
            'is_active' => 'nullable|boolean',
            'price' => 'nullable|integer',
        ]);

        try {
            // Create the exam
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
    public function examPlayersScoreList(Exam $exam)
    {
        $exam = $exam->load([
            'student_exams' => fn($qry) => $qry->select(['id', 'student_id', 'exam_id'])
                ->with(['student:id,name'])
                ->withCount('correct_answers')
                ->orderBy('correct_answers_count', 'DESC')
                ->orderBy('id', 'DESC')
        ]);

        $data = new \App\Http\Resources\ExamResource($exam);
        return Response::apiSuccess('exam with its lists of players with scores', $data);
    }
}
