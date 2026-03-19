<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Blog\AdminBlogCategoryRequest;
use App\Http\Resources\Admin\Blog\AdminBlogCategoryResource;
use App\Models\Blog\BlogCategories;
use App\Traits\ResponseTrait;
use App\Traits\NewPaginationTrait;
use App\Traits\PaginationTrait;
use Illuminate\Http\Request;

class AdminBlogCategoryController extends Controller
{
    //
    use ResponseTrait, NewPaginationTrait;
    /**
     * @OA\Get(
     *     path="/admin/blog/category",
     *     summary="Get category list",
     *     description="Returns paginated list of categories with search.",
     *     tags={"Admin Blog Category"},
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
     *         description="Search by category title",
     *         required=false,
     *         @OA\Schema(type="string", example="lens")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Category list returned successfully",
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
        $pagination = BlogCategories::when($search != null, fn($qry) => $qry->whereLike('title', '%' . $search . '%'))
            ->latest('id')
            ->paginate($per_page);
        $category = $this->makePaginationResponse($pagination, fn($item) => AdminBlogCategoryResource::collection($item))->data;
        return $this->apiSuccess('Blog list returned successfully', $category);
    }
    /**
     * @OA\Get(
     *     path="/admin/blog/category/{slug}",
     *     summary="Get single category detail",
     *     description="Returns detail of a specific category.",
     *     operationId="showCategory",
     *     tags={"Admin Blog Category"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Category slug",
     *         @OA\Schema(type="string", example="test")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Category detail retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category"),
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
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     * )
     */

    function show(BlogCategories $category)
    {
        return $this->apiSuccess("Category ", new AdminBlogCategoryResource($category));
    }
    /**
     * @OA\Post(
     *     path="/admin/blog/category",
     *     summary="Create a new category",
     *     description="Stores a new category.",
     *     tags={"Admin Blog Category"},
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
     *         description="Category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category created successfull")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    function store(AdminBlogCategoryRequest $request)
    {
        $category = BlogCategories::create($request->validated());
        return $this->apiSuccess("Category created successfull");
    }
    /**
     * @OA\Put(
     *     path="/admin/blog/category/{slug}",
     *     summary="Update category",
     *     description="Updates an existing category.",
     *     tags={"Admin Blog Category"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Category slug",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="title", type="string", example="Updated Category Name"),
     *             @OA\Property(property="slug", type="string", example="updated-category-name")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category updated successfull")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */

    function update(AdminBlogCategoryRequest $request, BlogCategories $category)
    {
        $category->update($request->validated());
        return $this->apiSuccess("Category updated successfull");
    }
    /**
     * @OA\Delete(
     *     path="/admin/blog/category/{slug}",
     *     summary="Delete category",
     *     description="Deletes a category by ID.",
     *     tags={"Admin Blog Category"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Category slug",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Category deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category deleted successfull")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Category not found")
     * )
     */

    function destroy(BlogCategories $category)
    {
        $category->delete();
        return $this->apiSuccess("Category deleted successfull");
    }
}
