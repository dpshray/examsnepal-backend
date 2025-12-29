<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ExamTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\TeacherExamStoreRequest;
use App\Http\Requests\Teacher\UpdateTeacherExamRequest;
use App\Http\Resources\ExamCollection;
use App\Http\Resources\ExamResource;
use App\Http\Resources\Teacher\TeacherExamDetailResource;
use App\Http\Resources\Teacher\TeacherExamResource;
use App\Models\Exam;
use App\Models\StudentProfile;
use App\Services\FCMService;
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
     *     @OA\Parameter(
     *         name="exam_type",
     *         in="query",
     *         required=false,
     *         description="exam_type",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Teacher exam list",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2544),
     *                         @OA\Property(property="published", type="integer", example=1),
     *                         @OA\Property(
     *                             property="exam_type",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Medical NMCLE / Loksewa / MDMS Exams")
     *                         ),
     *                         @OA\Property(
     *                             property="category_type",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="MOCK_TEST")
     *                         ),
     *                         @OA\Property(property="exam_name", type="string", example="Nerea Guy"),
     *                         @OA\Property(property="live", type="integer", example=1),
     *                         @OA\Property(property="description", type="string", example="Et sunt molestias re"),
     *                         @OA\Property(property="assign", type="integer", example=1),
     *                         @OA\Property(property="total_questions", type="integer", example=0)
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=2167),
     *                 @OA\Property(property="total", type="integer", example=2167)
     *             ),
     *             @OA\Property(property="message", type="string", example="teacher(TEST ADMIN) exam list")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $per_page = $request->query('per_page', 10);
        $examTypeId   = $request->query('exam_type');
        $category_type = $request->query('category_type');
        $teacher = Auth::guard('users')->user();
        $pagination = Exam::query()
            ->when(!$teacher->isAdmin(), fn($qry) => $qry->where('user_id', $teacher->id))
            ->when($examTypeId, function ($q) use ($examTypeId) {
                $q->where('exam_type_id', $examTypeId);
            })
            ->when($category_type, fn($qry) => $qry->where('status', $category_type))
            ->with(['examType:id,name'])
            ->withCount(['questions'])
            ->orderBy('id', 'DESC')
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
     *             @OA\Property(property="publish", type="integer", example=1),
     *             @OA\Property(property="assign", type="integer", example=1),
     *             @OA\Property(property="live", type="integer", example=1),
     *             @OA\Property(property="is_negative_marking", type="integer", example=0),
     *             @OA\Property(property="negative_marking_point", type="integer", example=0),
     *             @OA\Property(property="points_per_question", type="integer", example=1),
     *             @OA\Property(property="duration", type="string", example="02:45"),
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
        // return $request->validated();
        // dd($request->all());
        $data = $request->validated();
        $data['is_active'] = $request->publish;
        $data['status'] = $request->category_type;
        $exam = Auth::user()
        ->teacherExams()
        ->createQuietly($data);

        $type = strtolower(str_replace('_', ' ', ExamTypeEnum::getKeyByValue($exam->status)));
        
        if ($data['is_active'] == 1 && (bool)$request->assign && (bool)$request->live) {

            // get students who match exam type
            $students = StudentProfile::where('exam_type_id', $exam->exam_type_id)
                ->get();

            if (!empty($students)) {
                $fcmService = new FCMService(
                    'New Exam',
                    'A new ' . $type . ' exam has been added by your teacher. Please check and start preparing for it.',
                    $type,
                    $students->pluck('id')->toArray()
                );
                // send notification to all tokens
                $fcmService->notify($students->pluck('fcm_token')->toArray());
            }
        }

        return Response::apiSuccess('exam added successfully');
    }

    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/teacher/exam/{exam}",
     *     summary="Get exam detail",
     *     description="Get exam detail",
     *     operationId="teacher_exam_detail",
     *     tags={"TeacherExam"},
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="Exam id of exam",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam detail fetched successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="exam_type_id", type="integer", example=5),
     *                 @OA\Property(property="category_type", type="integer", example=1),
     *                 @OA\Property(property="exam_name", type="string", example="This is some exam name"),
     *                 @OA\Property(property="description", type="string", example="This is some description of exam"),
     *                 @OA\Property(property="publish", type="boolean", example=false),
     *                 @OA\Property(property="assign", type="boolean", example=false),
     *                 @OA\Property(property="live", type="boolean", example=false),
     *                 @OA\Property(property="is_negative_marking", type="boolean", example=false),
     *                 @OA\Property(property="negative_marking_point", type="number", format="float", example=0),
     *                 @OA\Property(property="points_per_question", type="integer", example=1),
     *                 @OA\Property(property="duration", type="string", example="03:00")
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="exam detail fetched successfully"
     *             )
     *         )
     *     )
     * )
     */
    public function show(Exam $exam)
    {
        $data = new TeacherExamDetailResource($exam);
        return Response::apiSuccess('exam detail fetched successfully', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    /**
     * @OA\Put(
     *     path="/teacher/exam/{exam}",
     *     summary="Update an exam (via teacher)",
     *     description="Update an existing exam. (Note: For 'publish', value must be either 1 or 0)",
     *     operationId="teacher_exam_update",
     *     tags={"TeacherExam"},
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="ID of the exam to update",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"exam_type_id","category_type","exam_name","description","publish"},
     *             @OA\Property(property="exam_type_id", type="integer", example=5),
     *             @OA\Property(property="category_type", type="integer", example=1),
     *             @OA\Property(property="exam_name", type="string", example="Updated Exam Name"),
     *             @OA\Property(property="description", type="string", example="Updated exam description"),
     *             @OA\Property(property="publish", type="integer", example=0),
     *             @OA\Property(property="assign", type="integer", example=0),
     *             @OA\Property(property="live", type="integer", example=1),
     *             @OA\Property(property="is_negative_marking", type="integer", example=0),
     *             @OA\Property(property="negative_marking_point", type="integer", example=0),
     *             @OA\Property(property="points_per_question", type="integer", example=1),
     *             @OA\Property(property="duration", type="string", example="02:45")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam Updated Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="exam updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized action",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="Unauthorized action")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Exam not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="Exam not found")
     *         )
     *     )
     * )
     */

    public function update(TeacherExamStoreRequest $request, Exam $exam)
    {
        //
        $this->isOwner($exam);
        $data = $request->validated();
        $data['status'] = $request->category_type;
        $data['is_active'] = $request->publish;
        $exam->updateQuietly($data);
        $type = strtolower(str_replace('_', ' ', ExamTypeEnum::getKeyByValue($exam->status)));
        if ($data['is_active'] == 1 && (bool)$request->assign && (bool)$request->live) {

            // get students who match exam type
            $students = StudentProfile::where('exam_type_id', $exam->exam_type_id)
                ->get();

            if (!empty($students)) {
                $fcmService = new FCMService(
                    'New Exam',
                    'A new '.$type.' exam has been added by your teacher. Please check and start preparing for it.',
                    $type,
                    $students->pluck('id')->toArray()
                );
                // send notification to all tokens
                $fcmService->notify($students->pluck('fcm_token')->toArray());
            }
        }
        return Response::apiSuccess('exam updated successfully');
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
        throw_if(!Auth::user()->isAdmin() && $exam->user->isNot(Auth::guard('users')->user()), AuthorizationException::class, 'You are not the owner');
    }
}
