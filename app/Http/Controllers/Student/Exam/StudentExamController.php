<?php

namespace App\Http\Controllers\Student\Exam;

use App\Http\Controllers\Controller;
use App\Http\Resources\Student\Exam\StudentExamDetailCollection;
use App\Http\Resources\Student\Exam\StudentExamDetailResource;
use App\Http\Resources\Student\ExamAttempt\StudentExamAttemptResource;
use App\Http\Resources\Student\ExamQuestion\StudentExamQuestionCollection;
use App\Models\Corporate\CorporateExam;
use App\Models\Corporate\CorporateExamSection;
use App\Models\Corporate\CorporateQuestion;
use App\Models\ExamAttempt;
use App\Models\ParticipantExam;
use App\Traits\PaginatorTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentExamController extends Controller
{
    //
    use PaginatorTrait;
    function examtype(CorporateExam $exams)
    {
        $examtype = $exams->exam_type;
        return response()->json(
            [
                'exam_type' => $examtype,
            ]
        );
    }
    /**
     * Get exam introduction and sections.
     *
     * @OA\Get(
     *     path="/exams/{exams}/examsdetail",
     *     summary="Get exam intro and sections",
     *     tags={"Corporate Student Exam"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="exams",
     *         in="path",
     *         required=true,
     *         description="Exam slug",
     *         @OA\Schema(type="string", example="")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Exam intro data",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="list of section in exam"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=10),
     *                 @OA\Property(property="corporate_exam_id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Exam Section A-45"),
     *                 @OA\Property(property="detail", type="string", example="Detailed description of Exam Section A-45"),
     *                 @OA\Property(property="is_published", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-02T12:00:00Z")
     *             ),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required"
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="You do not have access to this exam"
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Exam not found or not published"
     *     )
     * )
     */

    function examIntro($slug)
    {
        $token = JWTAuth::parseToken()->getPayload();
        // $per_page = $request->query('per_page', 12);
        $exam = CorporateExam::where('slug', $slug)
            ->where('is_published', true)
            ->with(['sections'])
            ->first();
        if (!$exam) {
            return Response::apiError('exam not found or not published');
        }

        // Check if participant has access (for private exams)
        if ($exam->exam_type === 'private') {
            $participant = Auth::guard('participant')->user();

            if (!$participant) {
                return Response::apiError('Authentication required');
            }

            $hasAccess = ParticipantExam::where('corporate_exam_id', $exam->id)
                ->where('participant_id', $participant->id)
                ->exists();

            if (!$hasAccess) {
                return Response::apiError('You do not have access to this exam');
            }

            // Get attempt count for private exam
            $attemptCount = ExamAttempt::where('corporate_exam_id', $exam->id)
                ->where('participant_id', $participant->id)
                ->count();
        } else {
            // For public exam, get attempt count by email
            $email = $token->get('email');
            $attemptCount = ExamAttempt::where('corporate_exam_id', $exam->id)
                ->where('email', $email)
                ->whereNull('participant_id')
                ->count();
        }
        // $pagination = $exam->loadMissing('sections')->paginate($per_page);
        // $data = $this->setupPagination($pagination, StudentExamDetailCollection::class)->data;
        $data = new StudentExamDetailResource($exam);
        return Response::apiSuccess("list of section in exam", $data);
    }
    /**
     * Start exam section attempt.
     *
     * @OA\Post(
     *     path="/exam/{exam}/section/{section}/startexam",
     *     summary="Start a section exam attempt",
     *     tags={"Corporate Student Exam"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="Corporate Exam Slug",
     *         @OA\Schema(type="string", example="")
     *     ),
     *
     *     @OA\Parameter(
     *         name="section",
     *         in="path",
     *         required=true,
     *         description="Exam SectionSlug",
     *         @OA\Schema(type="string", example="")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Exam attempt started",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Attempt detail"),
     *               @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="attempt_id", type="integer", example=4),
     *                 @OA\Property(property="exam_id", type="integer", example=1),
     *                 @OA\Property(property="section_id", type="integer", example=1),
     *                 @OA\Property(property="student_id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Ram"),
     *                 @OA\Property(property="email", type="string", example="example@gmail.com"),
     *                 @OA\Property(property="phone", type="string", example="980000000"),
     *
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Attempt limit exceeded"
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required"
     *     )
     * )
     */

    function startsectionexam(CorporateExam $exam, CorporateExamSection $section)
    {
        $token = JWTAuth::parseToken()->getPayload();
        // Check attempt limit at EXAM level (not section level)
        // If exam allows 1 attempt, user can attempt each section once
        if ($exam->exam_type === 'public') {
            $email = $token->get('email');

            // Check section-specific attempts
            $sectionAttemptCount = ExamAttempt::where('corporate_exam_id', $exam->id)
                ->where('corporate_exam_section_id', $section->id)
                ->where('email', $email)
                ->whereNull('participant_id')
                ->count();
        } else {
            $participant = Auth::guard('participant')->user();
            if (!$participant) {
                return Response::apiError('Authentication required');
            }

            // Check section-specific attempts
            $sectionAttemptCount = ExamAttempt::where('corporate_exam_id', $exam->id)
                ->where('corporate_exam_section_id', $section->id)
                ->where('participant_id', $participant->id)
                ->count();
        }

        // Each section can be attempted up to exam's limit_attempts times
        if ($exam->limit_attempts > 0 && $sectionAttemptCount >= $exam->limit_attempts) {
            return Response::apiError('You have reached the maximum number of attempts for this section');
        }
        // Get totalsum of full_marks
        $totalMarks = CorporateQuestion::where('corporate_exam_section_id', $section->id)
            ->sum('full_marks');

        // Create exam attempt
        $attemptData = [
            'corporate_exam_id' => $exam->id,
            'corporate_exam_section_id' => $section->id,
            'attempt_number' => $sectionAttemptCount + 1,
            'started_at' => Carbon::now(),
            'status' => 'started',
            'total_mark' => $totalMarks,
        ];

        // Add data based on exam type
        if ($exam->exam_type === 'public') {
            $attemptData['participant_id'] = null;
            $attemptData['name'] = $token->get('name');
            $attemptData['email'] = $token->get('email');
            $attemptData['phone'] = $token->get('phone');
        } else {
            $participant = Auth::guard('participant')->user();
            $attemptData['participant_id'] = $participant->id;
            $attemptData['name'] = $participant->name;
            $attemptData['email'] = $participant->email;
            $attemptData['phone'] = $participant->phone;
        }
        $attempt = ExamAttempt::create($attemptData);
        $attempt = new StudentExamAttemptResource($attempt);
        return Response::apiSuccess("Attempt detail", $attempt);
    }
    /**
     * Get questions for an exam attempt.
     *
     * @OA\Get(
     *     path="/get-question/{attempt_id}",
     *     summary="Get questions for an active exam attempt",
     *     tags={"Corporate Student Exam"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="attempt_id",
     *         in="path",
     *         required=true,
     *         description="Exam attempt ID",
     *         @OA\Schema(type="integer", example=100)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Questions per page",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Section question list",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="section question list"),
     *              @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=4),
     *                 @OA\Property(property="section_id", type="integer", example=1),
     *                 @OA\Property(property="question_type", type="string", example="MCQ"),
     *                 @OA\Property(property="question", type="string", example="What is 2 + 2?"),
     *                 @OA\Property(property="description", type="string", example="Basic math question"),
     *                 @OA\Property(property="full_marks", type="integer", example=5),
     *                 @OA\Property(property="negative_marks", type="integer", example=1),
     *
     *                 @OA\Property(
     *                     property="options",
     *                     type="array",
     *                     nullable=true,
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(property="option", type="string", example="4"),
     *
     *                     )
     *                 ),
     *
     *                 @OA\Property(property="created_at", type="string", example="2025-01-10 10:00:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-01-10 10:00:00")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized access to this attempt"
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Exam attempt not found or already submitted"
     *     )
     * )
     */

    function getquestion(Request $request, $attempt_id)
    {
        $attempt = ExamAttempt::where('id', $attempt_id)
            ->where('status', 'started')
            ->with('section')
            ->first();
        if (!$attempt) {
            return Response::apiError('Exam attempt not found or already submitted');
        }
        // Verify ownership
        $token = JWTAuth::parseToken()->getPayload();
        $exam = $attempt->exam;

        if ($exam->exam_type === 'public') {
            if ($attempt->email !== $token->get('email')) {
                return Response::apiError('Unauthorized access to this attempt');
            }
        } else {
            $participant = Auth::guard('participant')->user();
            if (!$participant || $attempt->participant_id !== $participant->id) {
                return Response::apiError('Unauthorized access to this attempt');
            }
        }
        $per_page = $request->input('per_page', 10);
        $questionsQuery = CorporateQuestion::where('corporate_exam_section_id', $attempt->corporate_exam_section_id)
            ->with(['options', 'studentAnswers' => function ($query) use ($attempt_id) {
                $query->where('exam_attempts_id', $attempt_id);
            }]);
        $section = CorporateExamSection::find($attempt->corporate_exam_section_id);
        // If exam question shuffled is on
        if ($exam->is_shuffled_question) {
            $questionsQuery->inRandomOrder($attempt->id);
        }
        $questions = $questionsQuery->paginate($per_page);

        // Transform all questions - add numbering and shuffle options if needed
        $questions->getCollection()->transform(function ($question, $index) use ($attempt, $questions, $exam) {
            // Set question number for ALL questions (1, 2, 3, etc.)
            $question->number = $questions->firstItem() + $index;

            // If question option shuffled is on, shuffle options for MCQ/objective
            if ($exam->is_shuffled_option) {
                // Only shuffle for MCQ/objective type questions
                if ($question->question_type === 'mcq' || $question->question_type === 'objective') {
                    // Create a unique seed for each question using attempt_id + question_id
                    // This ensures the same shuffle order every time for consistency
                    $seed = crc32($attempt->id . '_' . $question->id);

                    // Shuffle options with seed
                    $options = $question->options->shuffle($seed);

                    // Replace the options collection with shuffled one
                    $question->setRelation('options', $options);
                }
            }

            return $question;
        });

        $data = $this->setupPagination($questions, StudentExamQuestionCollection::class)->data;
        $response = array_merge(
            [
                'section_id' => $section->id,
                'title'      => $section->title,
                'slug'       => $section->slug,
                'detail'     => $section->detail,
                'duration'   => $exam->duration,
            ],
            $data
        );
        return Response::apiSuccess("section question list", $response);
    }
}
