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
        $doubts = Doubt::select('id','doubt','created_at','updated_at','status','exam_id','student_id','org_id','question_id')
        ->where('status','1')
        ->with('exam:id,exam_name','student:id,name','organization:id,fullname','question:id,question')
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
     *     path="/doubt",
     *     summary="Store a new doubt",
     *     tags={"Doubts"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="title", type="string", example="What is Laravel?"),
     *             @OA\Property(property="description", type="string", example="I want to know about Laravel.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Resource stored successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Resource stored successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        //
        return response()->json([
            'message' => 'Resource stored successfully.',
            'data' => $request->all()
        ], 201);
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
     *     path="/doubt/{id}",
     *     summary="Update a doubt",
     *     tags={"Doubts"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Doubt ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="title", type="string", example="Updated Doubt Title"),
     *             @OA\Property(property="description", type="string", example="Updated Description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resource updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Resource updated successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request, string $id)
    {
        //
        return response()->json([
            'message' => "Resource with ID $id updated successfully.",
            'data' => $request->all()
        ], 200);
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
        $doubt=Doubt::find($id);
        if(!$doubt){
            return response()->json([
                'success'=>false,
            'message'=>'Doubt not found',
            ],404);
        }
        $doubt->delete();

        return response()->json([
            'success'=>true,
            'message' => "Resource with ID $id deleted successfully."
        ], 200);
    }
}
