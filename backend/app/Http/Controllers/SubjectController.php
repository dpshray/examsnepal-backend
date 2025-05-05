<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/subjects",
     *     summary="Get list of all subjects",
     *     tags={"Subjects"},
     *     @OA\Response(
     *         response=200,
     *         description="List of subjects retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Mathematics"),
     *                     @OA\Property(property="code", type="string", example="MATH101"),
     *                     @OA\Property(property="description", type="string", example="Basic Mathematics"),
     *                     @OA\Property(property="status", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-19T12:00:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-19T12:30:00Z")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        //
        $subjects = Cache::get('subjects');
        return Response::apiSuccess('List of available subjects', $subjects);
        // return response()->json(['success' => true, 'data' => $subjects]);
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
     *     path="/subject",
     *     summary="Create a new subject",
     *     tags={"Subjects"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Physics"),
     *             @OA\Property(property="code", type="string", example="PHY101"),
     *             @OA\Property(property="description", type="string", example="Basic Physics Course"),
     *             @OA\Property(property="status", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Subject created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subject created successfully!"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Physics"),
     *                 @OA\Property(property="code", type="string", example="PHY101"),
     *                 @OA\Property(property="description", type="string", example="Basic Physics Course"),
     *                 @OA\Property(property="status", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-19T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-19T12:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="name", type="array",
     *                     @OA\Items(type="string", example="The name field is required.")
     *                 ),
     *                 @OA\Property(property="code", type="array",
     *                     @OA\Items(type="string", example="The code has already been taken.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        //
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:subjects,code',
            'description' => 'nullable|string',
            'status' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $subject = Subject::create($request->all());

        return response()->json(['success' => true, 'message' => 'Subject created successfully!', 'data' => $subject]);
    }

    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/subject/{id}",
     *     summary="Get details of a specific subject",
     *     tags={"Subjects"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the subject",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subject details retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Mathematics"),
     *                 @OA\Property(property="code", type="string", example="MATH101"),
     *                 @OA\Property(property="description", type="string", example="Basic Mathematics Course"),
     *                 @OA\Property(property="status", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-19T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-19T12:30:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Subject not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Subject not found")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        //
        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json(['success' => false, 'message' => 'Subject not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $subject]);
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
     *     path="/subject/{id}",
     *     summary="Update an existing subject",
     *     tags={"Subjects"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the subject to update",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Advanced Mathematics"),
     *             @OA\Property(property="code", type="string", example="MATH102"),
     *             @OA\Property(property="description", type="string", example="Advanced level Mathematics"),
     *             @OA\Property(property="status", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subject updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subject updated successfully!"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Advanced Mathematics"),
     *                 @OA\Property(property="code", type="string", example="MATH102"),
     *                 @OA\Property(property="description", type="string", example="Advanced level Mathematics"),
     *                 @OA\Property(property="status", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-19T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-19T12:30:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Subject not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Subject not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="name", type="array",
     *                     @OA\Items(type="string", example="The name field is required.")
     *                 ),
     *                 @OA\Property(property="code", type="array",
     *                     @OA\Items(type="string", example="The code has already been taken.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        //
        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json(['success' => false, 'message' => 'Subject not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => ['nullable', 'string', 'max:50', Rule::unique('subjects', 'code')->ignore($id)],
            'description' => 'nullable|string',
            'status' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $subject->update($request->all());

        return response()->json(['success' => true, 'message' => 'Subject updated successfully!', 'data' => $subject]);
    }

    /**
     * Remove the specified resource from storage.
     */

    /**
     * @OA\Delete(
     *     path="/subject/{id}",
     *     summary="Delete a specific subject",
     *     tags={"Subjects"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the subject to delete",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subject deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subject deleted successfully!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Subject not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Subject not found")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        //
        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json(['success' => false, 'message' => 'Subject not found'], 404);
        }

        $subject->delete();

        return response()->json(['success' => true, 'message' => 'Subject deleted successfully!']);
    }
}
