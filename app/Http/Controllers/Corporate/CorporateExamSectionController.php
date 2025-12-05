<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\CorporateExamQuestionRequest;
use App\Http\Requests\Corporate\CorporateExamSectionRequest;
use App\Http\Resources\Corporate\CorporateExamSectionCollection;
use App\Http\Resources\Corporate\CorporateExamSectionResource;
use App\Models\Corporate\CorporateExam;
use App\Models\Corporate\CorporateExamSection;
use App\Traits\PaginatorTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class CorporateExamSectionController extends Controller
{
    use PaginatorTrait;
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/corporate/exam/section/list/{exam}",
     *     summary="Fetch all exams of a corporate",
     *     description="Fetch all exams of a corporate",
     *     operationId="CorporateExamsSectionList",
     *     tags={"corporateExamSection"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="Corprate exam ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
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
     *         description="Items per page",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of corporate exams for a user",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="corporate_id", type="integer", example=197),
     *                         @OA\Property(property="title", type="string", example="corporate exam NO 51"),
     *                         @OA\Property(property="exam_date", type="string", format="date", example="2025-07-09"),
     *                         @OA\Property(property="start_time", type="string", format="time", example="10:00:00"),
     *                         @OA\Property(property="end_time", type="string", format="time", example="14:00:00")
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=1),
     *                 @OA\Property(property="total", type="integer", example=2)
     *             ),
     *             @OA\Property(property="message", type="string", example="corporate exam of user")
     *         )
     *     )
     * )
     */
    public function index(Request $request, CorporateExam $exam)
    {
        $this->itemBelongsToUser($exam);

        $per_page = $request->query('per_page', 12);
        $published = $request->query('published', 1);
        $pagination = $exam->sections()->where('is_published', $published)->paginate($per_page);
        $data = $this->setupPagination($pagination, CorporateExamSectionCollection::class)->data;
        return Response::apiSuccess("corporate section of exam: {$exam->title}", $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    /**
     * @OA\Post(
     *     path="/corporate/exam/section",
     *     summary="Create a new corporate exam",
     *     description="Store a new corporate exam section in the database.",
     *     operationId="corporateExamSectionCreate",
     *     tags={"corporateExamSection"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Corporate Exam data to be stored",
     *         @OA\JsonContent(
     *             required={"corporate_exam_id","title","detail"},
     *             @OA\Property(property="corporate_exam_id", type="integer", example="1"),
     *             @OA\Property(property="title", type="string", example="Exam section A-45"),
     *             @OA\Property(property="detail", type="string", example="Exam section A-45 desctiption"),
     *             @OA\Property(property="is_published", type="integer", example=1),
     *
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Corporate exam added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="corporate exam added successfully")
     *         )
     *     )
     * )
     */
    public function store(CorporateExamSectionRequest $request)
    {
        $form_data = $request->validated();
        $exam = CorporateExam::findOrFail($form_data['corporate_exam_id']);
        $this->itemBelongsToUser($exam);
        CorporateExamSection::create($form_data);
        return Response::apiSuccess('exam section created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(CorporateExamSection $section)
    {

    }

    /**
     * Update the specified resource in storage.
     */
    /**
     * @OA\Put(
     *     path="/corporate/exam/section/{section}",
     *     summary="Update an existing exam",
     *     description="Update an exam's details in the database.",
     *     operationId="corporateExamSectionUpdate",
     *     tags={"corporateExamSection"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="section",
     *         in="path",
     *         required=true,
     *         description="ID of the corporate exam section to be updated",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Exam data to be updated",
     *         @OA\JsonContent(
     *             required={"corporate_exam_id","title","detail"},
     *             @OA\Property(property="title", type="string", example="Exam section A-45"),
     *             @OA\Property(property="detail", type="string", example="Exam section A-45 desctiption"),
     *             @OA\Property(property="is_published", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Corporate exam updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="corporate exam updated")
     *         )
     *     )
     * )
     */
    public function update(CorporateExamSectionRequest $request, CorporateExamSection $section)
    {
        $this->itemBelongsToUser($section->exam);

        $form_data = $request->validated();
        $section->update($form_data);
        return Response::apiSuccess('exam section updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/corporate/exam/section/{section}",
     *     summary="Delete an corporate exam by ID",
     *     description="Delete an corporate exam from(exam id)",
     *     operationId="corporateExamSectionDelete",
     *     tags={"corporateExamSection"},
     *     @OA\Parameter(
     *         name="section",
     *         in="path",
     *         required=true,
     *         description="ID of the exam to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Corporate exam deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="corporate exam deleted")
     *         )
     *     )
     * )
     */
    public function destroy(CorporateExamSection $section)
    {
        $this->itemBelongsToUser($section->exam);
        $section->delete();
        return Response::apiSuccess('exam section deleted');
    }

    private function itemBelongsToUser(CorporateExam $exam){
        if ($exam->corporate->isNot(Auth::guard('users')->user())) {
            throw new AuthorizationException('You do not have permission to do this.');
        }
    }
}
