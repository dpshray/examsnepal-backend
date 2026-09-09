<?php

namespace App\Http\Controllers\Admin\ForumQuestionReport;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ForumQuestionReport\AdminForumQuestionReportResource;
use App\Models\ForumAnswer;
use App\Models\ForumQuestion;
use App\Models\ForumQuestionAnswerReport;
use App\Traits\NewPaginationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AdminForumQuestionReportController extends Controller
{
    //
    use NewPaginationTrait;
    /**
     * @OA\Get(
     *     path="/admin/forum-question-report",
     *     summary="Get all forum question reports",
     *     security={{"bearerAuth": {}}},
     *     tags={"Forum Question Report"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             example=10
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="question_id",
     *         in="query",
     *         description="Question ID",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Forum question reports fetched successfully"
     *     )
     * )
     */
    function index(Request $request)
    {
        $per_page = $request->per_page;
        $question_id = $request->question_id;
        $report = ForumQuestionAnswerReport::with(['question', 'answer', 'student'])->when($question_id, function ($query) use ($question_id) {
            return $query->where('question_id', $question_id);
        })->latest()->paginate($per_page);
        $data = $this->makePaginationResponse($report, fn($items) => AdminForumQuestionReportResource::collection($items))->data;
        return Response::apiSuccess('Report fetched successfully', $data);
    }
    /**
     * @OA\Delete(
     *     path="/admin/forum-question-report/{id}",
     *     summary="Delete a forum question report",
     *     security={{"bearerAuth": {}}},
     *     tags={"Forum Question Report"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Report ID",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Forum question report deleted successfully"
     *     )
     * )
     */
    function delete($id)
    {
        $report = ForumQuestionAnswerReport::find($id);
        if (!$report) {
            return Response::apiError('Report not found', 404);
        }
        if (!empty($report->forum_answer_id)) {
            $answer = ForumAnswer::find($report->forum_answer_id);
            $answer->update([
                'is_deleted' => '1'
            ]);
        } else {
            $question = ForumQuestion::find($report->forum_question_id);
            $question->update([
                'deleted' => '1'
            ]);
        }
        return Response::apiSuccess('Report deleted successfully', null, 200);
    }
}
