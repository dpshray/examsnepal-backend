<?php

namespace App\Http\Controllers\Admin\Exam\Type;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Exam\Type\AdminExamTypeResource;
use App\Models\ExamType;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AdminExamTypeController extends Controller
{
    //
    use PaginatorTrait;
    /**
     * @OA\Get(
     *     path="/admin/examtype",
     *     summary="Fetch all list of exam types",
     *     description="Fetch all exam types with title,excerpt and timestamps",
     *     operationId="ExamTypeList",
     *     tags={"Admin Exam Type"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Search term",
     *         @OA\Schema(type="string", example="Search")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved exam types",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Exam types retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="is_active", type="boolean"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No solutions found for this exam",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="No solutions found for this exam.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - The user is not authenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     */
    function index(Request $request)
    {
        $per_page = $request->query('per_page', 99);
        $search = $request->query('search');
        $examtype = ExamType::when($search, fn($qry) => $qry->where('name', 'like', '%' . $search . '%'))
            ->orderBy('id', 'DESC')
            ->paginate($per_page);
        $data = $this->setupPagination($examtype, AdminExamTypeResource::class);
        return Response::apiSuccess("Exam types", $data);
    }
    /**
     * @OA\Get(
     *     path="/admin/examtype/{id}",
     *     summary="Fetch a single exam type",
     *     description="Fetch a single exam type by ID",
     *     operationId="ExamTypeShow",
     *     tags={"Admin Exam Type"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the exam type",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved exam type",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Exam type retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Exam type not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Exam type not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - The user is not authenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     */
    function show(ExamType $examtype)
    {
        $data = new AdminExamTypeResource($examtype);
        return Response::apiSuccess("Exam type", $data);
    }
    /**
     * @OA\Post(
     *     path="/admin/examtype",
     *     summary="Create a new exam type",
     *     description="Create a new exam type",
     *     operationId="ExamTypeStore",
     *     tags={"Admin Exam Type"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "is_active"},
     *             @OA\Property(property="name", type="string", example="Exam Type"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam type created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Exam type created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - The user is not authenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     */
    function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'is_active' => 'required',
        ]);
        $examtype = ExamType::create($data);
        return Response::apiSuccess("Exam type created successfully");
    }
    /**
     * @OA\Put(
     *     path="/admin/examtype/{id}",
     *     summary="Update an exam type",
     *     description="Update an exam type by ID",
     *     operationId="ExamTypeUpdate",
     *     tags={"Admin Exam Type"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the exam type",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "is_active"},
     *             @OA\Property(property="name", type="string", example="Exam Type"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam type updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Exam type updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - The user is not authenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     */
    function update(Request $request, ExamType $examtype)
    {
        $data = $request->validate([
            'name' => 'required',
            'is_active' => 'required',
        ]);
        $examtype->update($data);
        return Response::apiSuccess("Exam type updated successfully");
    }
    /**
     * @OA\Delete(
     *     path="/admin/examtype/{id}",
     *     summary="Delete an exam type",
     *     description="Delete an exam type by ID",
     *     operationId="ExamTypeDelete",
     *     tags={"Admin Exam Type"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the exam type",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam type deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Exam type deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - The user is not authenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     */
    function destroy(ExamType $examtype)
    {
        $examtype->delete();
        return Response::apiSuccess("Exam type deleted successfully");
    }
}
