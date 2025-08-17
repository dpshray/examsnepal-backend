<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\TeacherExamStoreRequest;
use App\Http\Resources\ExamCollection;
use App\Http\Resources\ExamResource;
use App\Http\Resources\Teacher\TeacherExamResource;
use App\Models\Exam;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Auth\Access\AuthorizationException;

class TeacherExamController extends Controller
{
    use PaginatorTrait;
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/teacher/exam",
     *     summary="Get all exam of logged in teacher",
     *     description="Fetches all exam of logged in teacher.",
     *     operationId="teacher_exam_list",
     *     tags={"TeacherExam"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="page no of list",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="items per page",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Teacher (Loksewa) Exam List with Total Questions",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=368),
     *                         @OA\Property(property="publised", type="integer", example=0),
     *                         @OA\Property(
     *                             property="exam_type",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="name", type="string", example="Administration Loksewa all levels Exams")
     *                         ),
     *                         @OA\Property(property="exam_name", type="string", example="Loksewa Online Exam for Nasu & Kharidar"),
     *                         @OA\Property(property="total_questions", type="integer", example=50)
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=1),
     *                 @OA\Property(property="total", type="integer", example=9)
     *             ),
     *             @OA\Property(property="message", type="string", example="teacher(loksewa) exam list")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $per_page = $request->query('per_page',10);
        $teacher = Auth::guard('users')->user();
        $pagination = $teacher->teacherExams()
                        ->with(['examType:id,name'])
                        ->withCount(['questions'])
                        ->orderBy('id','DESC')
                        ->paginate($per_page);
        $data = $this->setupPagination($pagination, fn($item) => TeacherExamResource::collection($item))->data;
        return Response::apiSuccess("teacher({$teacher->username}) exam list", $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    /**
     * @OA\Post(
     *     path="/teacher/exam",
     *     summary="Add an exam (via teacher)",
     *     description="Add an exam. (Note: For 'publish', value must be either 1 or 0)",
     *     operationId="teacher_exam",
     *     tags={"TeacherExam"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"exam_type_id","category_type","exam_name","description","publish"},
     *             @OA\Property(property="exam_type_id", type="integer", example=5),
     *             @OA\Property(property="category_type", type="integer", example=1),
     *             @OA\Property(property="exam_name", type="string", example="This is some exam name"),
     *             @OA\Property(property="description", type="string", example="This is some description of exam"),
     *             @OA\Property(property="publish", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam Added Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="exam added successfully")
     *         )
     *     )
     * )
     */

    public function store(TeacherExamStoreRequest $request)
    {
        // dd($request->all());
        $data = $request->all();
        Auth::user()
            ->teacherExams()
            ->createQuietly($data);
        return Response::apiSuccess('exam added successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Exam $exam)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exam $exam)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/teacher/exam/{exam}",
     *     summary="Delete an exam",
     *     description="Delete an exam by its ID.",
     *     operationId="deleteTeacherExam",
     *     tags={"TeacherExam"},
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         description="The ID of the exam to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="exam name This is some exam name 1 deleted")
     *         )
     *     )
     * )
     */

    public function destroy(Exam $exam)
    {
        $this->isOwner($exam);
        $exam_name = $exam->exam_name;
        $exam->delete();
        return Response::apiSuccess("exam name {$exam_name} deleted");
    }

    private function isOwner(Exam $exam)
    {
        throw_if($exam->user->isNot(Auth::guard('users')->user()), AuthorizationException::class, 'You are not the owner');
    }
}
