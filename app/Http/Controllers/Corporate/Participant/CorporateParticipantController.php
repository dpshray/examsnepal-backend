<?php

namespace App\Http\Controllers\Corporate\Participant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Participant\ParticipantRequest;
use App\Http\Resources\Corporate\Participant\CorporateParticipantCollection;
use App\Http\Resources\Corporate\Participant\CorporateParticipantResource;
use App\Models\Corporate\CorporateExam;
use App\Models\Participant;
use App\Models\ParticipantExam;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;


class CorporateParticipantController extends Controller
{
    //
    use PaginatorTrait;
    /**
     * Store participants from an Excel file.
     *
     * @OA\Post(
     *     path="/corporate/participants/import",
     *     summary="Import Participants from Excel",
     *     tags={"Corporate Participants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     description="Excel file to upload (xlsx, xls)",
     *                     type="string",
     *                     format="binary"
     *                 ),
     *                 required={"file"}
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Participants imported successfully from Excel",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Participants imported successfully")
     *         )
     *     )
     * )
     */

    public function store_from_excel(Request $request)
    {
        //Import from Excel
        $user = Auth::user();
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file')->getRealPath();

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

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
        }

        return Response::apiSuccess('Participants imported successfully from Excel');
    }
    /**
     * Store a newly created participant in storage.
     * @OA\Post(
     *     path="/corporate/participants",
     *     summary="Create Participant",
     *     tags={"Corporate Participants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="phone", type="string", example="1234567890"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *            @OA\Property(property="password", type="string", format="password", example="password123")
     *        )
     *    ),
     *    @OA\Response(
     *        response=200,
     *       description="Participant created successfully from form",
     *       @OA\JsonContent(
     *          @OA\Property(property="success", type="boolean"),
     *         @OA\Property(property="message", type="string"),
     *    )
     *   )
     * )
     */
    public function store(ParticipantRequest $request, CorporateExam $exam)
    {
        $data = $request->validated();
        $user = Auth::user();

        // Create participant if not exists (by corporate_id + email)
        $participant = Participant::firstOrCreate(
            [
                'corporate_id' => $user->id,
                'email'        => $data['email'],
            ],
            [
                'name'     => $data['name'],
                'phone'    => $data['phone'],
                'password' => Hash::make($data['password']),
                'raw_password' => $data['password'],
            ]
        );

        // If participant already existed
        if (! $participant->wasRecentlyCreated) {
            return Response::apiError('This email already exists');
        }

        // Attach participant to exam (prevent duplicate)
        ParticipantExam::firstOrCreate([
            'corporate_exam_id' => $exam->id,
            'participant_id'    => $participant->id,
        ]);

        return Response::apiSuccess('Participant created successfully from form');
    }

    /**
     * Display a listing of the participants.
     *
     * @OA\Get(
     *    path="/corporate/participants",
     *    summary="Get Participants",
     *    tags={"Corporate Participants"},
     *    security={{"bearerAuth":{}}},
     *
     *    @OA\Parameter(
     *        name="per_page",
     *        in="query",
     *        description="Number of items per page",
     *        required=false,
     *        @OA\Schema(type="integer", default=10)
     *    ),
     *
     *    @OA\Parameter(
     *        name="search",
     *        in="query",
     *        description="Search term (name, phone, email)",
     *        required=false,
     *        @OA\Schema(type="string")
     *    ),
     *
     *    @OA\Response(
     *        response=200,
     *        description="Participants retrieved successfully",
     *        @OA\JsonContent(
     *            @OA\Property(property="success", type="boolean", example=true),
     *            @OA\Property(property="message", type="string", example="Participants retrieved successfully"),
     *            @OA\Property(
     *                property="data",
     *                type="object",
     *                @OA\Property(
     *                    property="items",
     *                    type="array",
     *                    @OA\Items(
     *                        @OA\Property(property="id", type="integer", example=1),
     *                        @OA\Property(property="name", type="string", example="John Doe"),
     *                        @OA\Property(property="phone", type="string", example="9876543210"),
     *                        @OA\Property(property="email", type="string", example="john@example.com"),
     *                        @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T10:00:00Z"),
     *                        @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-02T10:00:00Z"),
     *                    )
     *                ),
     *                @OA\Property(property="pagination", type="object",
     *                    @OA\Property(property="current_page", type="integer", example=1),
     *                    @OA\Property(property="per_page", type="integer", example=10),
     *                    @OA\Property(property="total", type="integer", example=55),
     *                    @OA\Property(property="last_page", type="integer", example=6)
     *                )
     *            )
     *        )
     *    )
     * )
     */

    function index(Request $request)
    {
        $user = Auth::user();
        $per_page = $request->input('per_page', 10);
        $search = $request->input('search', '');
        $participants = Participant::where('corporate_id', $user->id)
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
                $q->orWhere('email', 'like', "%{$search}%");
            })->paginate($per_page);
        $data = $this->setupPagination($participants, CorporateParticipantCollection::class)->data;
        return Response::apiSuccess('Participants retrieved successfully', $data);
    }
    /**
     * Display the specified participant.
     *
     * @OA\Get(
     *     path="/corporate/participants/{id}",
     *     summary="Get Participant by ID",
     *     tags={"Corporate Participants"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Participant ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Participant retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Participant retrieved successfully"),
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="phone", type="string", example="9876543210"),
     *                 @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T10:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-02T10:00:00Z")
     *             )
     *         )
     *     )
     * )
     */
    function show(CorporateExam $exam, Participant $participant)
    {
        $user = Auth::user();
        if ($participant->corporate_id != $user->id) {
            return Response::apiError('Unauthorized', [], 403);
        }
        $data = new CorporateParticipantResource($participant);
        return Response::apiSuccess('Participant retrieved successfully', $data);
    }
    /**
     * Remove the specified participant from storage.
     * @OA\Delete(
     *   path="/corporate/participants/{id}",
     *  summary="Delete Participant",
     *  tags={"Corporate Participants"},
     *  security={{"bearerAuth":{}}},
     *  @OA\Parameter(
     *      name="id",
     *     in="path",
     *    description="Participant ID",
     *    required=true,
     *   @OA\Schema(type="integer")
     * ),
     *  @OA\Response(
     *     response=200,
     *    description="Participant deleted successfully",
     *   @OA\JsonContent(
     *      @OA\Property(property="success", type="boolean", example=true),
     *     @OA\Property(property="message", type="string", example="Participant deleted successfully")
     *  )
     * )
     * )
     */
    function destroy(CorporateExam $exam, Participant $participant)
    {
        $user = Auth::user();
        if ($participant->corporate_id != $user->id) {
            return Response::apiError('Unauthorized', [], 403);
        }
        if ($exam->exam_type == 'private') {
            ParticipantExam::where('participant_id', $participant->id)
                ->where('corporate_exam_id', $exam->id)
                ->delete();
        }
        $participant->delete();
        return Response::apiSuccess('Participant deleted successfully');
    }
    /**
     * Update the specified participant in storage.
     * @OA\Put(
     *    path="/corporate/participants/{id}",
     *   summary="Update Participant",
     *   tags={"Corporate Participants"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *       name="id",
     *      in="path",
     *     description="Participant ID",
     *    required=true,
     *   @OA\Schema(type="integer")
     *  ),
     *  @OA\RequestBody(
     *      required=true,
     *     @OA\JsonContent(
     *        required={"name","email"},
     *       @OA\Property(property="name", type="string", example="John Doe"),
     *      @OA\Property(property="phone", type="string", example="1234567890"),
     *     @OA\Property(property="email", type="string", format="email", example="john.doe@example.com")
     *     )
     *  ),
     *  @OA\Response(
     *     response=200,
     *    description="Participant updated successfully",
     *   @OA\JsonContent(
     *      @OA\Property(property="success", type="boolean", example=true),
     *     @OA\Property(property="message", type="string", example="Participant updated successfully")
     *  )
     * )
     * )
     */
    public function update(CorporateExam $exam, ParticipantRequest $request, Participant $participant)
    {
        $user = Auth::user();

        // Authorization check
        if ($participant->corporate_id !== $user->id) {
            return Response::apiError('Unauthorized', [], 403);
        }

        $data = $request->validated();

        // Prevent duplicate email within same corporate
        $emailExists = Participant::where('corporate_id', $user->id)
            ->where('email', $data['email'])
            ->where('id', '!=', $participant->id)
            ->exists();

        if ($emailExists) {
            return Response::apiError('This email already exists');
        }

        // Update participant
        $participant->update([
            'name'  => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => !empty($data['password'])
                ? Hash::make($data['password'])
                : $participant->password,
            'raw_password' => $data['password'] ?? $participant->raw_password,
        ]);

        return Response::apiSuccess('Participant updated successfully');
    }

    /**
     * Bulk delete participants.
     *
     * @OA\Delete(
     *   path="/corporate/participants/bulk-delete",
     *   summary="Bulk Delete Participants",
     *   tags={"Corporate Participants"},
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"ids"},
     *       @OA\Property(
     *         property="ids",
     *         type="array",
     *         @OA\Items(
     *           type="integer",
     *           example=1
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Participants deleted successfully"
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error"
     *   )
     * )
     */
    function bulk_delete(CorporateExam $exam, Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:participants,id',
        ]);
        if ($exam->exam_type == 'private') {
            foreach ($data['ids'] as $id) {
                $participant = Participant::find($id);
                if ($participant && $participant->corporate_id == $user->id) {
                    ParticipantExam::where('participant_id', $participant->id)->where('corporate_exam_id', $exam->id)->delete();
                    // $participant->delete();
                }
            }
        }
        return Response::apiSuccess('Participants deleted successfully');
    }
}
