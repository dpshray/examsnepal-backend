<?php

namespace App\Http\Controllers\Corporate\Exam;

use App\Http\Controllers\Controller;
use App\Http\Resources\Corporate\ExamParticipant\ExamParticipantCollection;
use App\Models\Corporate\CorporateExam;
use App\Models\Participant;
use App\Models\ParticipantExam;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class AddParticipantToExamController extends Controller
{
    //
    use PaginatorTrait;
    /**
     * Get participants assigned to a corporate exam.
     *
     * @OA\Get(
     *     path="/corporate/exams/{exam}/participants",
     *     summary="List participants assigned to a specific corporate exam",
     *     tags={"Corporate Exams Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="ID of the corporate exam",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of participants per page (pagination)",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of participants",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=12),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="phone", type="string", example="9876543210")
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="last_page", type="integer", example=5)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Exam not found"
     *     )
     * )
     */
    function index(Request $request, CorporateExam $exam)
    {
        $perPage = $request->get('per_page', 10);
        $participants = $exam->participants()->paginate($perPage);
        $data = $this->setupPagination($participants, ExamParticipantCollection::class)->data;
        return Response::apiSuccess('list of Participants in exam', $data);
    }
    /**
     * Add participants to a corporate exam.
     *
     * @OA\Post(
     *     path="/corporate/exams/{exam}/participants",
     *     summary="Add participants to a specific corporate exam",
     *     tags={"Corporate Exams Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="ID of the corporate exam",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(
     *                 property="ids",
     *                 type="array",
     *                 description="Array of participant IDs to add to the exam",
     *                 @OA\Items(type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Participants added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Participants add successfully to exam")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Exam not found"
     *     )
     * )
     */
    function store(Request $request, CorporateExam $exam)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:participants,id',
        ]);
        DB::transaction(function () use ($data, $exam) {
            foreach ($data['ids'] as $id) {
                ParticipantExam::create([
                    'corporate_exam_id' => $exam->id,
                    'participant_id' => $id
                ]);
            }
        });
        return Response::apiSuccess('Participants add successfully to exam');
    }
    /**
     * Remove a participant from a corporate exam.
     *
     * @OA\Delete(
     *     path="/corporate/exams/participants/{pexam}",
     *     summary="Delete a participant from a corporate exam",
     *     tags={"Corporate Exams Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="pexam",
     *         in="path",
     *         required=true,
     *         description="ID of the participant_exam pivot record",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Participant removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Participants has been deleted from exam")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="ParticipantExam record not found"
     *     )
     * )
     */
    function destroy(ParticipantExam $pexam)
    {
        $pexam->delete();
        return Response::apiSuccess('Participants has been deleted from exam');
    }
}
