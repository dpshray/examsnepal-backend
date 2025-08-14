<?php

namespace App\Http\Controllers;

use App\Enums\ExamTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ExamCategoryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    /**
     * @OA\Get(
     *     path="/category-types",
     *     summary="Get all exam category types",
     *     description="Fetches a list of exams categories.",
     *     operationId="exam_category",
     *     tags={"ExamCategory"},
     *     @OA\Response(
     *         response=200,
     *         description="Exam Category List",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="name", type="string", example="FREE_QUIZ")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="exam category list")
     *         )
     *     )
     * )
     */

    public function __invoke(Request $request)
    {
        $exam_category = collect(ExamTypeEnum::cases())->map(fn($item) => ['id' => $item->value, 'name' => $item->name]);
        return Response::apiSuccess('exam category list', $exam_category);
    }
}
