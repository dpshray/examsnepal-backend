<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exam;
use Illuminate\Support\Facades\Auth;

class BankQuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    /**
     * @OA\Get(
     *     path="/all-question-banks",
     *     summary="Get all question banks",
     *     description="Returns a list of exams that are marked as question banks",
     *     operationId="getQuestionBanks",
     *     tags={"Question Banks"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of all Question Banks retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="exam_name", type="string", example="Math Final"),
     *                 @OA\Property(property="exam_date", type="string", format="date", example="2025-06-10"),
     *                 @OA\Property(property="is_question_bank", type="boolean", example=true),
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
        $questionBanks = Exam::where('is_question_bank', true)
            ->get(['id', 'exam_name as bank_name']);
        return response()->json($questionBanks);
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
     *     path="/question-bank",
     *     summary="Create a new Question Bank",
     *     description="Creates a question bank by storing it in the exams table with is_question_bank set to true.",
     *     operationId="storeQuestionBank",
     *     tags={"Question Banks"},
     *     security={{"bearerAuth":{}}},
     * 
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"assign_id","question_bank_name","exam_type_id"},
     *             @OA\Property(property="assign_id", type="integer", example=1, description="Assigned user's ID"),
     *             @OA\Property(property="question_bank_name", type="string", example="Physics MCQs Bank", description="Name of the question bank"),
     *             @OA\Property(property="exam_type_id", type="integer", example=2, description="Type ID of the exam (foreign key)")
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=201,
     *         description="Question Bank created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Question Bank created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=12),
     *                 @OA\Property(property="user_id", type="integer", example=3),
     *                 @OA\Property(property="exam_name", type="string", example="Physics MCQs Bank"),
     *                 @OA\Property(property="status", type="string", example=null),
     *                 @OA\Property(property="assign_id", type="integer", example=1),
     *                 @OA\Property(property="is_question_bank", type="boolean", example=true),
     *                 @OA\Property(property="exam_type_id", type="integer", example=2),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=500,
     *         description="Failed to store Question Bank",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to store Question Bank"),
     *             @OA\Property(property="message", type="string", example="SQLSTATE[23000]: Integrity constraint violation ...")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        //
        $userId = Auth::id();


        try {

            $validatedData = $request->validate([
                'assign_id' => 'required|integer|exists:users,id',
                'question_bank_name' => 'required|string|max:255',
                'exam_type_id' => 'required|integer|exists:exam_types,id',
            ]);

            $bank = Exam::create([
                'user_id' => $userId,
                'exam_name' => $validatedData['question_bank_name'],
                'status' => null,
                'assign_id' => $validatedData['assign_id'],
                'is_question_bank' => 1,
                'exam_type_id' => $validatedData['exam_type_id'],
            ]);

            return response()->json([
                'message' => 'Question Bank created successfully',
                'data' => $bank
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to store Question Bank',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/question-bank/{id}",
     *     summary="Get a specific Question Bank",
     *     description="Fetch a Question Bank by its ID",
     *     operationId="getQuestionBankById",
     *     tags={"Question Banks"},
     *     security={{"bearerAuth":{}}},
     * 
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the Question Bank",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     * 
     *     @OA\Response(
     *         response=200,
     *         description="Question Bank fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Question Bank fetched successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=3),
     *                 @OA\Property(property="exam_name", type="string", example="General Knowledge Bank"),
     *                 @OA\Property(property="assign_id", type="integer", example=1),
     *                 @OA\Property(property="exam_type_id", type="integer", example=2),
     *                 @OA\Property(property="is_question_bank", type="boolean", example=true),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=404,
     *         description="Question Bank not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Question Bank not found")
     *         )
     *     )
     * )
     */

    public function show(string $id)
    {
        //
        $bank = Exam::find($id);

        if (!$bank || !$bank->is_question_bank) {
            return response()->json(['error' => 'Question Bank not found'], 404);
        }

        return response()->json([
            'message' => 'Question Bank fetched successfully',
            'data' => $bank,
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
     *     path="/question-bank/{id}",
     *     summary="Update a Question Bank",
     *     description="Update the details of a specific Question Bank by its ID",
     *     operationId="updateQuestionBank",
     *     tags={"Question Banks"},
     *     security={{"bearerAuth":{}}},
     * 
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the Question Bank to update",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     * 
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"assign_id", "question_bank_name", "exam_type_id"},
     *             @OA\Property(property="assign_id", type="integer", example=1),
     *             @OA\Property(property="question_bank_name", type="string", example="Updated Bank Title"),
     *             @OA\Property(property="exam_type_id", type="integer", example=2)
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=200,
     *         description="Question Bank updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Question Bank updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=3),
     *                 @OA\Property(property="exam_name", type="string", example="Updated Bank Title"),
     *                 @OA\Property(property="assign_id", type="integer", example=1),
     *                 @OA\Property(property="exam_type_id", type="integer", example=2),
     *                 @OA\Property(property="is_question_bank", type="boolean", example=true),
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=404,
     *         description="Question Bank not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Question Bank not found")
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=500,
     *         description="Failed to update Question Bank",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to update Question Bank"),
     *             @OA\Property(property="message", type="string", example="Exception message...")
     *         )
     *     )
     * )
     */
    public function update(Request $request, string $id)
    {
        $bank = Exam::find($id);

        if (!$bank || !$bank->is_question_bank) {
            return response()->json(['error' => 'Question Bank not found'], 404);
        }

        try {
            $validatedData = $request->validate([
                'assign_id' => 'required|integer|exists:users,id',
                'question_bank_name' => 'required|string|max:255',
                'exam_type_id' => 'required|integer|exists:exam_types,id',
            ]);

            $bank->exam_name = $validatedData['question_bank_name'];
            $bank->assign_id = $validatedData['assign_id'];
            $bank->exam_type_id = $validatedData['exam_type_id'];
            $bank->save();

            return response()->json([
                'message' => 'Question Bank updated successfully',
                'data' => $bank,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update Question Bank',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/question-bank/{id}",
     *     summary="Delete a Question Bank",
     *     description="Deletes a specific question bank by its ID.",
     *     operationId="deleteQuestionBank",
     *     tags={"Question Banks"},
     *     security={{"bearerAuth":{}}},
     * 
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the Question Bank to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     * 
     *     @OA\Response(
     *         response=200,
     *         description="Bank deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Bank deleted successfully")
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=404,
     *         description="Bank not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Bank not found")
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=500,
     *         description="Failed to delete bank",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to delete bank"),
     *             @OA\Property(property="message", type="string", example="Exception message...")
     *         )
     *     )
     * )
     */

    public function destroy($id)
    {
        //
        $bank = Exam::find($id);

        if (!$bank) {
            return response()->json(['error' => 'Bank not found'], 404);
        }

        try {
            $bank->delete();

            return response()->json(['message' => 'Bank deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete bank',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
