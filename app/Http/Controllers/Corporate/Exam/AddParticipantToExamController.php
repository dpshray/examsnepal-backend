<?php

namespace App\Http\Controllers\Corporate\Exam;

use App\Http\Controllers\Controller;
use App\Http\Resources\Corporate\ExamParticipant\ExamParticipantCollection;
use App\Models\Corporate\CorporateExam;
use App\Models\Participant;
use App\Models\ParticipantExam;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rules\Exists;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
     *         @OA\Schema(type="string", example="")
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
     *         @OA\Schema(type="string", example="")
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
     * Remove participants from a corporate exam (bulk delete).
     *
     * @OA\Delete(
     *     path="/corporate/exams/participants",
     *     summary="Remove participants from a corporate exam",
     *     tags={"Corporate Exams Participants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(
     *                 property="ids",
     *                 type="array",
     *                 description="Array of participant_exam pivot IDs",
     *                 @OA\Items(type="string", example="")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Participants removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Participants has been deleted from exam"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="One or more ParticipantExam records not found"
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    function destroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:participant_exams,id',
        ]);

        ParticipantExam::whereIn('id', $request->ids)->delete();

        return Response::apiSuccess('Participants has been deleted from exam');
    }

    /**
     * Bulk upload participants and add them to a corporate exam.
     *
     * @OA\Post(
     *     path="/corporate/exams/{exam}/bulk-upload-participants",
     *     summary="Bulk upload participants and add them to an exam",
     *     tags={"Corporate Exams Participants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="Corporate Exam slug",
     *         @OA\Schema(type="string", example="")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Excel or CSV file (name, phone, email, password)"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Participants uploaded and added to exam successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Participants uploaded and added to exam successfully"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */

    public function bulk_upload_in_exam(Request $request, CorporateExam $exam)
    {
        $user = Auth::user();

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        DB::transaction(function () use ($rows, $exam, $user) {

            foreach ($rows as $index => $row) {

                if ($index === 0) continue; // skip header

                $email = $row[2] ?? null;
                if (!$email) continue;


                //Find or Create Participant
                $participant = Participant::firstOrCreate(
                    [
                        'corporate_id' => $user->id,
                        'email'        => $email,
                    ],
                    [
                        'name'     => $row[0] ?? null,
                        'phone'    => $row[1] ?? null,
                        'password' => isset($row[3]) ? Hash::make($row[3]) : null,
                        'raw_password' => isset($row[3]) ? $row[3] : null,
                    ]
                );


                //Check if participant already added to exam

                $alreadyAdded = ParticipantExam::where('corporate_exam_id', $exam->id)
                    ->where('participant_id', $participant->id)
                    ->exists();

                if ($alreadyAdded) {
                    continue; // skip if already in exam
                }

                //Add participant to exam
                ParticipantExam::create([
                    'corporate_exam_id' => $exam->id,
                    'participant_id'    => $participant->id,
                ]);
            }
        });

        return Response::apiSuccess('Participants uploaded and added to exam successfully');
    }
}
