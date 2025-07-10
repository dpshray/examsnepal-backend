<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\CorporateExamRequest;
use App\Http\Resources\Corporate\CorporateExamCollection;
use App\Http\Resources\Corporate\CorporateExamResource;
use App\Models\Corporate\CorporateExam;
use App\Traits\PaginatorTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class CorporateExamController extends Controller
{
    use PaginatorTrait;
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/corporate/exam",
     *     summary="Fetch all exams of a corporate",
     *     description="Fetch all exams of a corporate",
     *     operationId="CorporateExamsList",
     *     tags={"CorporateExams"},
     *     security={{"bearerAuth": {}}},
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
     *     @OA\Parameter(
     *         name="published",
     *         in="query",
     *         required=false,
     *         description="Corporate exam of status: published",
     *         @OA\Schema(type="integer", example=1)
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
    public function index(Request $request)
    {
        $per_page = $request->query('per_page',12);
        $published = $request->query('published',1);
        $pagination = CorporateExam::where([
                            ['corporate_id', Auth::guard('users')->id()],
                            ['is_published', $published]
                        ])
                        ->paginate($per_page);
        $data = $this->setupPagination($pagination, CorporateExamCollection::class)->data;
        return Response::apiSuccess("corporate exam of user", $data);
    }

    /**
     * @OA\Post(
     *     path="/corporate/exam",
     *     summary="Create a new corporate exam",
     *     description="Store a new corporate exam in the database.",
     *     operationId="createCorporateExam",
     *     tags={"CorporateExams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Corporate Exam data to be stored",
     *         @OA\JsonContent(
     *             required={ "title", "exam_date", "start_time", "duration", "is_published"},
     *             @OA\Property(property="title", type="string", example="corporate exam NO 1"),
     *             @OA\Property(property="exam_date", type="string", format="date", example="2025-07-09"),
     *             @OA\Property(property="start_time", type="string", format="time", example="10:00"),
     *             @OA\Property(property="duration", type="integer", example="90"),
     *             @OA\Property(property="about", type="string", example="lorem ipsum dolor"),
     *             @OA\Property(property="rules", type="string", example="lorem ipsum dolor"),
     *             @OA\Property(property="is_published", type="integer", example=1)
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
    public function store(CorporateExamRequest $request)
    {
        $form_data = $request->validated();
        $form_data['corporate_id'] = Auth::guard('users')->id();
        CorporateExam::create($form_data);
        return Response::apiSuccess('corporate exam added successfully');
    }

    /**
     * Display the specified resource.
    */
    public function show(CorporateExam $corporateExam)
    {
        
    }

    /**
     * Update the specified resource in storage.
    */
    /**
     * @OA\Put(
     *     path="/corporate/exam/{exam}",
     *     summary="Update an existing exam",
     *     description="Update an exam's details in the database.",
     *     operationId="updateCorporateExam",
     *     tags={"CorporateExams"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="ID of the exam to be updated",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Exam data to be updated",
     *         @OA\JsonContent(
     *             required={ "title", "exam_date", "start_time", "duration", "is_published"},
     *             @OA\Property(property="title", type="string", example="corporate exam NO 1"),
     *             @OA\Property(property="exam_date", type="string", format="date", example="2025-07-09"),
     *             @OA\Property(property="start_time", type="string", format="time", example="10:00"),
     *             @OA\Property(property="duration", type="integer", example="90"),
     *             @OA\Property(property="about", type="string", example="lorem ipsum dolor"),
     *             @OA\Property(property="rules", type="string", example="lorem ipsum dolor"),
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
    public function update(CorporateExamRequest $request, CorporateExam $exam)
    {
        $this->itemBelongsToUser($exam);
        $form_data = $request->validated();
        $exam->update($form_data);
        return Response::apiSuccess('corporate exam updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/corporate/exam/{exam}",
     *     summary="Delete an corporate exam by ID",
     *     description="Delete an corporate exam from(exam id)",
     *     operationId="deleteCorporateExam",
     *     tags={"CorporateExams"},
     *     @OA\Parameter(
     *         name="exam",
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
    public function destroy(CorporateExam $exam)
    {
        $this->itemBelongsToUser($exam);
        $exam->delete();
        return Response::apiSuccess('corporate exam deleted');
    }   

    private function itemBelongsToUser(CorporateExam $corporate_exam){
        if ($corporate_exam->corporate->isNot(Auth::guard('users')->user())) {
            throw new AuthorizationException('You do not have permission to do this.');
        }
    }
}
