<?php

namespace App\Http\Controllers;

use App\Models\ExamType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ExamTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/exam-types",
     *     summary="Get all exam types",
     *     description="Retrieve a list of all exam types.",
     *     operationId="getExamTypes",
     *     tags={"Exam Types"},
     *     @OA\Response(
     *         response=200,
     *         description="A list of exam types",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="mdms"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-01T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-01T12:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Server error")
     *         )
     *     )
     * )
     */
    public function index()
    {
        $exam_types = ExamType::select('id','name')->where('is_active',1)->get();
        return Response::apiSuccess('Active exam types', $exam_types);
        // return response()->json($exam_types);
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
     *     path="/exam-types",
     *     summary="Create a new exam type (for Admin)",
     *     description="This endpoint creates a new exam type with a name and active status.",
     *     operationId="createExamType",
     *     tags={"Exam Types"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Exam type creation data",
     *         @OA\JsonContent(
     *             required={"name", "is_active"},
     *             @OA\Property(property="name", type="string", example="MCQ", description="Name of the exam type"),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Whether the exam type is active or not")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Exam type created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Exam type created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="mdms"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-01T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-01T12:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error (e.g., missing required fields)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="name", type="array", items=@OA\Items(type="string"), example={"The exam type name is required."}),
     *                 @OA\Property(property="is_active", type="array", items=@OA\Items(type="string"), example={"The status is required."})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to create exam type"),
     *             @OA\Property(property="message", type="string", example="Internal server error details")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Validate input with custom error messages
        $validator = $request->validate([
            'name' => 'required|string|unique:exam_types,name', // Ensure name is unique
            'is_active' => 'required|boolean'  // Ensure is_active is a boolean
        ]);

        // If validation fails, Laravel will automatically return a response with error messages
        // Create and return the exam type
        try {
            $examType = ExamType::create($request->only('name', 'is_active'));

            // Return success response with created data
            return response()->json([
                'message' => 'Exam type created successfully',
                'data' => $examType
            ], 201);
        } catch (\Exception $e) {
            // If an error occurs during creation, catch it and return error response
            return response()->json([
                'error' => 'Failed to create exam type',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/exam-types/{id}",
     *     summary="Get an exam type by ID (for Admin)",
     *     description="Retrieve the details of an exam type by its ID.",
     *     operationId="getExamTypeById",
     *     tags={"Exam Types"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the exam type to retrieve",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam type retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="MCQ"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-01T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-01T12:05:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Exam type not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Exam type not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve exam type"),
     *             @OA\Property(property="message", type="string", example="Internal server error details")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        try {
            // Find the exam type by ID
            $examType = ExamType::find($id);

            // If the exam type is not found, return a 404 response
            if (!$examType) {
                return response()->json(['error' => 'Exam type not found'], 404);
            }

            // Return the found exam type
            return response()->json(['data' => $examType]);
        } catch (\Exception $e) {
            // If an error occurs during fetching, catch it and return error response
            return response()->json([
                'error' => 'Failed to retrieve exam type',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ExamType $examType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    /**
     * @OA\Put(
     *     path="/exam-types/{id}",
     *     summary="Update an existing exam type (for Admin)",
     *     description="Update the details of an existing exam type.",
     *     operationId="updateExamType",
     *     tags={"Exam Types"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the exam type to be updated",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Exam type update data",
     *         @OA\JsonContent(
     *             required={"name", "is_active"},
     *             @OA\Property(property="name", type="string", example="MCQ", description="Name of the exam type"),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Whether the exam type is active or not")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam type updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Exam type updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="MCQ"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-01T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-01T12:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Exam type not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Exam type not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error (e.g., missing required fields)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="name", type="array", items=@OA\Items(type="string"), example={"The exam type name is required."}),
     *                 @OA\Property(property="is_active", type="array", items=@OA\Items(type="string"), example={"The status is required."})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to update exam type"),
     *             @OA\Property(property="message", type="string", example="Internal server error details")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            // Find the exam type by ID
            $examType = ExamType::find($id);

            // If the exam type is not found, return a 404 response
            if (!$examType) {
                return response()->json(['error' => 'Exam type not found'], 404);
            }

            // Validate the input data
            $request->validate([
                'name' => 'required|string|unique:exam_types,name,' . $id, // Ensure name is unique except for this ID
                'is_active' => 'required|boolean'
            ]);

            // Update the exam type with the validated data
            $examType->update($request->only('name', 'is_active'));

            // Return success response with updated data
            return response()->json([
                'message' => 'Exam type updated successfully',
                'data' => $examType
            ]);
        } catch (\Exception $e) {
            // If an error occurs during update, catch it and return error response
            return response()->json([
                'error' => 'Failed to update exam type',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/exam-types/{id}",
     *     summary="Delete an exam type (for Admin)",
     *     description="Delete an exam type by its ID.",
     *     operationId="deleteExamType",
     *     tags={"Exam Types"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the exam type to be deleted",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam type deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Exam type deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Exam type not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Exam type not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to delete exam type"),
     *             @OA\Property(property="message", type="string", example="Internal server error details")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            // Find the exam type by ID
            $examType = ExamType::find($id);

            // If the exam type is not found, return a 404 response
            if (!$examType) {
                return response()->json(['error' => 'Exam type not found'], 404);
            }

            // Delete the exam type
            $examType->delete();

            // Return success response
            return response()->json(['message' => 'Exam type deleted successfully']);
        } catch (\Exception $e) {
            // Catch any errors and return an error response
            return response()->json([
                'error' => 'Failed to delete exam type',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
