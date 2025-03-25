<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Doubt;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DoubtController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/doubts",
     *     summary="Get all doubts",
     *     tags={"Doubts"},
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
            ->get();

        return response()->json([
            'message' => 'Fetched all doubts successfully.',
            'data' => $doubts,
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
     *     path="/doubts",
     *     summary="Create a new doubt",
     *     description="Creates a new doubt in the system.",
     *     tags={"Doubts"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"doubt", "exam_id", "org_id", "question_id"},
     *             @OA\Property(property="doubt", type="string", example="This is a sample doubt."),
     *             @OA\Property(property="exam_id", type="integer", example=1),
     *             @OA\Property(property="org_id", type="integer", example=1),
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
        $studentId=Auth::id();
        try {
            $validatedData = $request->validate([
                'doubt' => 'required|string',
                'exam_id' => 'required|exists:exams,id',
                'org_id' => 'required|exists:users,id',
                'question_id' => 'required|exists:questions,id',
            ]);

            $validatedData['status'] = '1';
            $validatedData['student_id'] =$studentId;



            $doubt = Doubt::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Doubt successfully created',
                'data' => $doubt,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return validation errors in the response
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422); // HTTP 422 Unprocessable Entity
        } catch (\Exception $e) {
            // Handle other unexpected errors
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500); // HTTP 500 Internal Server Error
        }
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
     *     path="/doubts/{id}",
     *     summary="Update a specific doubt",
     *     description="Updates the details of a specific doubt if it has not been solved already.",
     *     operationId="updateDoubt",
     *     tags={"Doubts"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The ID of the doubt to update",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"doubt", "exam_id", "org_id", "question_id"},
     *                 @OA\Property(property="doubt", type="string", example="This is a new doubt."),
     *                 @OA\Property(property="exam_id", type="integer", example=123),
     *                 @OA\Property(property="org_id", type="integer", example=456),
     *                 @OA\Property(property="question_id", type="integer", example=789)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Doubt successfully updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Doubt successfully updated"),
     *             @OA\Property(property="data", type="object", 
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="doubt", type="string", example="This is a new doubt."),
     *                 @OA\Property(property="exam_id", type="integer", example=123),
     *                 @OA\Property(property="org_id", type="integer", example=456),
     *                 @OA\Property(property="question_id", type="integer", example=789),
     *                 @OA\Property(property="status", type="string", example="1")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="The doubt cannot be updated because it has already been solved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This doubt has already been solved and cannot be updated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Doubt not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Doubt not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="An unexpected error occurred",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred"),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */

    public function update(Request $request, $id)
    {
        try {
            $doubt = Doubt::find($id);
            if (!$doubt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Doubt not found.',
                ], 404);
            }

            $validatedData = $request->validate([
                'doubt' => 'required|string',
                'exam_id' => 'required|exists:exams,id',
                'org_id' => 'required|exists:users,id',
                'question_id' => 'required|exists:questions,id',
            ]);

            if ($doubt->status === '0' && !is_null($doubt->solved_by)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This doubt has already been solved and cannot be updated.',
                ], 400); // HTTP 400 Bad Request
            }

            $doubt->doubt = $validatedData['doubt'];
            $doubt->exam_id = $validatedData['exam_id'];
            $doubt->org_id = $validatedData['org_id'];
            $doubt->question_id = $validatedData['question_id'];
            $doubt->status = '1';

            $doubt->save();

            return response()->json([
                'success' => true,
                'message' => 'Doubt successfully updated',
                'data' => $doubt,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/doubt/{id}",
     *     summary="Delete a doubt",
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
     *         description="Resource deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Resource deleted successfully.")
     *         )
     *     )
     * )
     */
    public function destroy(string $id)
    {
        //
        $doubt = Doubt::find($id);
        if (!$doubt) {
            return response()->json([
                'success' => false,
                'message' => 'Doubt not found',
            ], 404);
        }
        $doubt->delete();

        return response()->json([
            'success' => true,
            'message' => "Resource with ID $id deleted successfully."
        ], 200);
    }
}
