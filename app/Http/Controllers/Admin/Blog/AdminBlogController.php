<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Blog\AdminBlogRequest;
use App\Http\Resources\Admin\Blog\AdminBlogResource;
use App\Models\Blog\Blog;
use App\Traits\NewPaginationTrait;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBlogController extends Controller
{
    //
    use ResponseTrait, NewPaginationTrait;
    /**
     * @OA\Get(
     *     path="/admin/blogs",
     *     summary="Get paginated list of blogs",
     *     description="Returns paginated blogs with optional search",
     *     tags={"Admin Blogs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by blog title",
     *         required=false
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog list returned successfully"
     *     )
     * )
     */
    function index(Request $request)
    {
        $per_page = $request->per_page;
        $search = $request->query('search', null);
        $pagination = Blog::when($search != null, fn($qry) => $qry->whereLike('title', '%' . $search . '%'))
            ->latest('id')
            ->paginate($per_page);
        $blog = $this->makePaginationResponse($pagination, fn($item) => AdminBlogResource::collection($item))->data;
        return $this->apiSuccess('Blog list returned successfully', $blog);
    }
    /**
     * @OA\Get(
     *     path="/admin/blogs/{slug}",
     *     summary="Get blog detail",
     *     description="Returns details of a single blog",
     *     tags={"Admin Blogs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="slug",
     *         required=true
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog detail returned successfully"
     *     )
     * )
     */
    function show(Blog $blog)
    {
        $blog = $blog->loadMissing(['category', 'tag']);
        return $this->apiSuccess('Blog detail returned successfully', new AdminBlogResource($blog));
    }
    /**
     * @OA\Post(
     *     path="/admin/blogs",
     *     summary="Create a new blog",
     *     description="Stores a new blog",
     *     tags={"Admin Blogs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", description="Blog title", type="string", example="My First Blog"),
     *                 @OA\Property(property="content", description="Full blog content", type="string", example="This is the content of the blog."),
     *                 @OA\Property(property="summary", description="Short summary of the blog", type="string", example="Short summary"),
     *                 @OA\Property(property="author", description="Author name", type="string", example="John Doe"),
     *                 @OA\Property(property="status", description="Blog status (draft/published)", type="string", example="published"),
     *                 @OA\Property(property="Call_to_Action", description="Optional call to action", type="string", example="Read more"),
     *                 @OA\Property(property="published_date", description="Published date", type="string", format="date", example="2025-11-24"),
     *                 @OA\Property(property="image", description="Blog image file", type="string", format="binary"),
     *                 @OA\Property(property="category_id", description="Array of category IDs", type="array", @OA\Items(type="integer"), example={1,2}),
     *                 @OA\Property(property="tag_id", description="Array of tag IDs", type="array", @OA\Items(type="integer"), example={1,3,5})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    function store(AdminBlogRequest $request)
    {
        DB::transaction(function () use ($request) {
            $blog = Blog::create($request->validated());
            $blog->category()->attach($request->category_id);
            $blog->tag()->attach($request->tag_id);
            $blog->addMedia($request->file('image'))->toMediaCollection(Blog::BLOG_IMAGE);
        });
        return $this->apiSuccess('Blog store successfull');
    }
    /**
     * @OA\Put(
     *     path="/admin/blogs/{slug}",
     *     summary="Update a blog",
     *     description="Updates an existing blog",
     *     tags={"Admin Blogs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *     name="slug",
     *     in="path",
     *     description="Blog slug",
     *     required=true
     * ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", description="Blog title", type="string", example="My First Blog"),
     *                 @OA\Property(property="content", description="Full blog content", type="string", example="This is the content of the blog."),
     *                 @OA\Property(property="summary", description="Short summary of the blog", type="string", example="Short summary"),
     *                 @OA\Property(property="author", description="Author name", type="string", example="John Doe"),
     *                 @OA\Property(property="status", description="Blog status (draft/published)", type="string", example="published"),
     *                 @OA\Property(property="Call_to_Action", description="Optional call to action", type="string", example="Read more"),
     *                 @OA\Property(property="published_date", description="Published date", type="string", format="date", example="2025-11-24"),
     *                 @OA\Property(property="image", description="Blog image file", type="string", format="binary"),
     *                 @OA\Property(property="category_id", description="Array of category IDs", type="array", @OA\Items(type="integer"), example={1,2}),
     *                 @OA\Property(property="tag_id", description="Array of tag IDs", type="array", @OA\Items(type="integer"), example={1,3,5})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog updated successfully"
     *     )
     * )
     */

    function update(AdminBlogRequest $request, Blog $blog)
    {
        // dd($request->all());
        DB::transaction(function () use ($request, $blog) {
            $blog->update($request->validated());
            $blog->category()->sync($request->category_id);
            $blog->tag()->sync($request->tag_id);
            if ($request->hasFile('image')) {
                $blog->clearMediaCollection(Blog::BLOG_IMAGE);
                $blog->addMedia($request->file('image'))->toMediaCollection(Blog::BLOG_IMAGE);
            }
        });
        return $this->apiSuccess('Blog update successfull');
    }
    /**
     * @OA\Delete(
     *     path="/admin/blogs/{slug}",
     *     summary="Delete a blog",
     *     description="Deletes a blog by ID",
     *     tags={"Admin Blogs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *     name="slug",
     *     in="path",
     *     description="Blog slug",
     *     required=true
     * ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog deleted successfully"
     *     )
     * )
     */
    function destroy(Blog $blog)
    {
        $blog->delete();
        return $this->apiSuccess('Blog delete successfull');
    }
}
