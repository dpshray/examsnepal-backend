<?php

namespace App\Http\Controllers\Admin\ExamTag;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ExamTag\AdminExamTagCollection;
use App\Http\Resources\Admin\ExamTag\AdminExamTagResource;
use App\Models\ExamTag;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AdminExamTagController extends Controller
{
    //
    use PaginatorTrait;
    /**
     * @OA\Get(
     *     path="/admin/exam-tags",
     *     summary="Get exam tags list",
     *     tags={"Admin Exam Tags"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Search by exam name",
     *         @OA\Schema(type="string", example="IELTS")
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of records per page",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Exam tags list"
     *     )
     * )
     */

    function index(Request $request)
    {
        $per_page = $request->query('per_page', 10);
        $search = $request->query('search');
        $tags = ExamTag::when($search, fn($qry) => $qry->where('name', 'like', '%' . $search . '%'))
            ->orderBy('id', 'DESC')
            ->paginate($per_page);
        $data = $this->setupPagination($tags, AdminExamTagCollection::class);
        return Response::apiSuccess('Exam tags list', $data);
    }
    /**
     * @OA\Get(
     *     path="/admin/exam-tags/{tag}",
     *     summary="Get exam tag details",
     *     tags={"Admin Exam Tags"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         required=true,
     *         description="Exam Tag ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Exam tag details"
     *     )
     * )
     */

    function show($tag)
    {
        $tag = ExamTag::where('slug', $tag)->first();
        $data = new AdminExamTagResource($tag);
        return Response::apiSuccess('Exam tag details', $data);
    }
    /**
     * @OA\Post(
     *     path="/admin/exam-tags",
     *     summary="Create exam tag",
     *     tags={"Admin Exam Tags"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 example="IELTS"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag created successfully"
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $tag = ExamTag::create($request->all());
        return Response::apiSuccess('Tag created successfully', $tag);
    }
    /**
     * @OA\Put(
     *     path="/admin/exam-tags/{tag}",
     *     summary="Update exam tag",
     *     tags={"Admin Exam Tags"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         required=true,
     *         description="Exam Tag ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 example="TOEFL"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag updated successfully"
     *     )
     * )
     */

    function update(Request $request, $tag)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $tag = ExamTag::where('slug', $tag)->first();
        $tag->update($data);
        return Response::apiSuccess('Tag updated successfully', $tag);
    }
    /**
     * @OA\Delete(
     *     path="/admin/exam-tags/{tag}",
     *     summary="Delete exam tag",
     *     tags={"Admin Exam Tags"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         required=true,
     *         description="Exam Tag ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag deleted successfully"
     *     )
     * )
     */

    function destroy($tag)
    {
        $tag = ExamTag::where('slug', $tag)->first();
        $tag->delete();
        return Response::apiSuccess('Tag deleted successfully', $tag);
    }
}
