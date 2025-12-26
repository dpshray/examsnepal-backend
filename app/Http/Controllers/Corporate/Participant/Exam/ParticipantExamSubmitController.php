<?php

namespace App\Http\Controllers\Corporate\Participant\Exam;

use App\Http\Controllers\Controller;
use App\Http\Resources\Corporate\Exam\Submission\AllSubmissionCollection;
use App\Http\Resources\Corporate\Exam\Submission\ParticipantExamDetailResource;
use App\Models\ExamAttempt;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class ParticipantExamSubmitController extends Controller
{
    //
    use PaginatorTrait;
    /**
     * @OA\Get(
     *     path="/corporate/exams/submitted-exams",
     *     summary="Get list of submitted exam attempts",
     *     description="Returns paginated list of submitted, evaluating, or evaluated exam attempts for the logged-in teacher's corporate exams.",
     *     tags={"Corporate Exam Submissions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of records per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Filter by student name",
     *         required=false,
     *         @OA\Schema(type="string", example="Ram")
     *     ),
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="Filter by student email",
     *         required=false,
     *         @OA\Schema(type="string", example="ram@gmail.com")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Submission status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"submitted","evaluating","evaluated"},
     *             example="submitted"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="exam_id",
     *         in="query",
     *         description="Filter by exam ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of submissions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="List Of All Submissions"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="total", type="integer", example=20),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="attempt_number", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Ram"),
     *                         @OA\Property(property="email", type="string", example="ram@gmail.com"),
     *                         @OA\Property(property="status", type="string", example="submitted"),
     *                         @OA\Property(property="submitted_at", type="string", example="2025-05-10 12:30:00")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    function index(Request $request)
    {
        $per_page = $request->query('per_page', 10);
        $name = $request->input('name');
        $email = $request->input('email');
        $status = $request->input('status'); //submitted, evaluating, evaluated
        $exam_id = $request->input('exam_id');
        $teacher = Auth::user();
        $corporate_id = $teacher->id;
        $query = ExamAttempt::query()
            ->with(['exam', 'section', 'participant'])
            // Only get attempts for this teacher's corporate exams
            ->whereHas('exam', function ($q) use ($corporate_id) {
                $q->where('corporate_id', $corporate_id);
            });
        // Filter by participant/student name
        if ($name) {
            $query->where('name', 'like', "%{$name}%");
        }

        // Filter by email
        if ($email) {
            $query->where('email', 'like', "%{$email}%");
        }
        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        // Filter by exam
        if ($exam_id) {
            $query->where('corporate_exam_id', $exam_id);
        }
        // Only get submitted attempts
        $query->whereIn('status', ['submitted', 'evaluating', 'evaluated']);
        // Order by latest first
        $query->orderBy('submitted_at', 'desc');
        $attempts = $query->paginate($per_page);
        $data = $this->setupPagination($attempts, AllSubmissionCollection::class)->data;
        return Response::apiSuccess('List Of All Submissions ', $data);
    }
    /**
     * @OA\Get(
     *     path="/corporate/exams/submitted-exams/{attempts}",
     *     summary="Get submitted exam detail",
     *     description="Returns detailed exam submission with student answers.",
     *     tags={"Corporate Exam Submissions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="attempts",
     *         in="path",
     *         required=true,
     *         description="Exam attempt ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Submission detail",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Submissions detail"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="attempt_number", type="integer", example=1),
     *
     *                 @OA\Property(
     *                     property="student",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=10),
     *                     @OA\Property(property="name", type="string", example="Ram"),
     *                     @OA\Property(property="email", type="string", example="ram@gmail.com"),
     *                     @OA\Property(property="phone", type="string", example="98XXXXXXXX")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="exam",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="title", type="string", example="PHP Final Exam"),
     *                     @OA\Property(property="exam_type", type="string", example="mcq"),
     *                     @OA\Property(property="duration", type="integer", example=60)
     *                 ),
     *
     *                 @OA\Property(
     *                     property="section",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="title", type="string", example="Section A")
     *                 ),
     *
     *                 @OA\Property(property="started_at", type="string", example="2025-05-10 11:30:00"),
     *                 @OA\Property(property="submitted_at", type="string", example="2025-05-10 12:30:00"),
     *                 @OA\Property(property="status", type="string", example="evaluated"),
     *
     *                 @OA\Property(property="total_mark", type="number", example=100),
     *                 @OA\Property(property="obtained_mark", type="number", example=85),
     *                 @OA\Property(property="percentage", type="number", example=85),
     *
     *                 @OA\Property(
     *                     property="answers",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="question", type="string", example="What is PHP?"),
     *                         @OA\Property(property="question_type", type="string", example="subjective"),
     *                         @OA\Property(property="full_marks", type="integer", example=10),
     *                         @OA\Property(property="marks_obtained", type="integer", example=8),
     *                         @OA\Property(property="student_answer", type="string", example="PHP is a scripting language"),
     *                         @OA\Property(property="correct_answer", type="string", nullable=true),
     *                         @OA\Property(property="is_correct", type="boolean", example=true),
     *                         @OA\Property(property="needs_evaluation", type="boolean", example=false)
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    function show(ExamAttempt $attempts)
    {
        $attempts = $attempts->loadMissing(['exam', 'section', 'participant','studentAnswers']);
        $data = new ParticipantExamDetailResource($attempts);
        return Response::apiSuccess('Submissions detail', $data);
    }

}
