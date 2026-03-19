<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Blog\AdminBlogTagRequest;
use App\Http\Resources\Admin\Blog\AdminBlogTagResource;
use App\Models\Blog\BlogTag;
use App\Traits\ResponseTrait;
use App\Traits\NewPaginationTrait;
use Illuminate\Http\Request;

class AdminBlogTagController extends Controller
{
    //
    use ResponseTrait, NewPaginationTrait;
    /**
     * @OA\Get(
     *     path="/admin/blog/tag",
     *     summary="Get tag list",
     *     description="Returns paginated list of Tag with search.",
     *     tags={"Admin Blog Tag"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by Tag title",
     *         required=false,
     *         @OA\Schema(type="string", example="lens")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag list returned successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blog list returned successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="total", type="integer", example=25),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Eyeglasses"),
     *                         @OA\Property(property="slug", type="string", example="eyeglasses"),
     *                         @OA\Property(property="created_at", type="string", example="2025-11-25 09:00:00"),
     *                         @OA\Property(property="updated_at", type="string", example="2025-11-25 09:00:00")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    function index(Request $request)
    {
        $per_page = $request->per_page;
        $search = $request->query('search', null);
        $pagination = BlogTag::when($search != null, fn($qry) => $qry->whereLike('title', '%' . $search . '%'))
            ->latest('id')
            ->paginate($per_page);
        $Tag = $this->makePaginationResponse($pagination, fn($item) => AdminBlogTagResource::collection($item))->data;
        return $this->apiSuccess('Tag list returned successfully', $Tag);
    }
    /**
     * @OA\Get(
     *     path="/admin/blog/tag/{slug}",
     *     summary="Get single Tag detail",
     *     description="Returns detail of a specific Tag.",
     *     operationId="showTag",
     *     tags={"Admin Blog Tag"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Tag slug",
     *         @OA\Schema(type="string", example="test")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag detail retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tag"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Sunglasses"),
     *                 @OA\Property(property="slug", type="string", example="sunglasses"),
     *                 @OA\Property(property="description", type="string", example="All types of eyewear sunglasses"),
     *                 @OA\Property(property="created_at", type="string", example="2025-10-21 10:15:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-10-25 05:20:00")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Tag not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     * )
     */

    function show(BlogTag $Tag)
    {
        return $this->apiSuccess("Tag ", new AdminBlogTagResource($Tag));
    }
    /**
     * @OA\Post(
     *     path="/admin/blog/tag",
     *     summary="Create a new Tag",
     *     description="Stores a new Tag.",
     *     tags={"Admin Blog Tag"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="title", type="string", example="Contact Lenses"),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tag created successfull")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    function store(AdminBlogTagRequest $request)
    {
        $Tag = BlogTag::create($request->validated());
        return $this->apiSuccess("Tag created successfull");
    }
    /**
     * @OA\Put(
     *     path="/admin/blog/tag/{slug}",
     *     summary="Update Tag",
     *     description="Updates an existing Tag.",
     *     tags={"Admin Blog Tag"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Tag slug",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="title", type="string", example="Updated Tag Name"),
     *             @OA\Property(property="slug", type="string", example="updated-Tag-name")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tag updated successfull")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tag not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */

    function update(AdminBlogTagRequest $request, BlogTag $Tag)
    {
        $Tag->update($request->validated());
        return $this->apiSuccess("Tag updated successfull");
    }
    /**
     * @OA\Delete(
     *     path="/admin/blog/tag/{slug}",
     *     summary="Delete Tag",
     *     description="Deletes a Tag by ID.",
     *     tags={"Admin Blog Tag"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Tag slug",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tag deleted successfull")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tag not found")
     * )
     */

    function destroy(BlogTag $Tag)
    {
        $Tag->delete();
        return $this->apiSuccess("Tag deleted successfull");
    }
}
