<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnswerStoreRequest;
use App\Http\Resources\QuestionCollection;
use Illuminate\Http\Request;
use App\Models\Answersheet;
use App\Models\Exam;
use App\Models\StudentExam;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class AnswerSheetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/submit-answer",
     *     operationId="submitStudentAnswers",
     *     tags={"Quiz"},
     *     summary="Saves question and option based on exam_id",
     *     description="Stores answers submitted by a student for a particular exam.",
     * 
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"exam_id", "question_id", "answer_id"},
     *             @OA\Property(
     *                 property="exam_id",
     *                 type="integer",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="question_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={180665, 180666}
     *             ),
     *             @OA\Property(
     *                 property="question_id",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={180665, 180666}
     *             ),
     *             @OA\Property(
     *                 property="option_id",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={684213, 684220}
     *             )
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=409,
     *         description="If student exam has been completed or the requested question has already been answered.",
     *         @OA\JsonContent(
     *         oneOf={
     *             @OA\Schema(
     *                 @OA\Property(property="message", type="string", example="Student has already submitted answers for this exam.")
     *             ),
     *             @OA\Schema(
     *                 @OA\Property(property="message", type="string", example="Requested questions has already been answered.")
     *             )
     *         }
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=422,
     *         description="Form validation error / Request question and answer Array lengths don't match.",
     *         @OA\JsonContent(
     *             oneOf={
     *             @OA\Schema(
     *                 @OA\Property(property="message", type="string", example="No. of questions must be equal to No. of options.")
     *             ),
     *             @OA\Schema(
     *                 @OA\Property(property="message", type="string", example="Validation Errror."),
     *                 @OA\Property(property="status", type="boolean", example="false."),
     *                 @OA\Property(property="data", type="object", example="")
     *             )
     *         }
     *         )
     *     ),
     * 
     *     @OA\Response(
     *         response=201,
     *         description="If answered saved.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Answer Saved Successfully.")
     *         )
     *     ),
     *     
     *      @OA\Response(
     *         response=200,
     *         description="If All questions has been answered",
     *         @OA\JsonContent(
     *             oneOf={
     *             @OA\Schema(
     *                 @OA\Property(property="message", type="string", example="All questions has been answered")
     *             ),
     *             @OA\Schema(
     *              @OA\Property(property="message", type="string", example="Answers submitted successfully!")
     * 
     *             )
     *         }
     *        )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'exam_id' => 'required|integer|exists:exams,id',
            'question_id' => 'nullable|array',
            'question_id.*' => 'integer|exists:questions,id',
            'option_id' => 'nullable|array',
            'option_id.*' => 'integer|exists:option_questions,id',
            'question_ids' => 'required|array',
            'question_ids.*' => 'integer|exists:option_questions,id'
        ]);
        $exam_id = $request->exam_id;
        $student = Auth::guard('api')->user();
        
        $received_questions = $validatedData['question_id'];
        $received_answers = $validatedData['option_id'];
        $all_question_ids = $validatedData['question_ids'];

        if (count($received_answers) != count($received_questions)) {
            return Response::apiError('No. of questions does not match with the No. of options.', null, 422);
        }

        $student_exam = $student->student_exams()->firstWhere('exam_id',$exam_id);
        if ($student_exam == null) {
            return Response::apiError('This exam has not been initialized properly(from page 1)', null, 422);
        }
        // if ($student_exam) {
        //     if ($student_exam->completed) {
        //         return Response::apiError('This exam has already been completed by this user.',null,409);
        //     }
            
        //     $already_answered = $student_exam->answers()->whereIn('question_id', $received_questions)->exists();
        //     if ($already_answered) {
        //         return Response::apiError('Requested questions has already been answered', null, 409);
        //     }
        // }
        /**
         * this code is commented so that response must
         * contains expected question_id and option_id
         * based on received exam_id
         */
        // $expected_options_and_questions = DB::table('exams')
        //     ->join('questions', 'exams.id', 'questions.exam_id')
        //     ->join('option_questions', 'questions.id', 'option_questions.question_id')
        //     ->select('questions.id as qid', 'option_questions.id as oid')
        //     ->where('exams.id', $exam_id)
        //     ->pluck('qid', 'oid')
        //     ->all();

        // $expected_option_ids = array_keys($expected_options_and_questions);
        // $expected_question_ids = array_unique($expected_options_and_questions);



        // $student_exam = $student->student_exams()->updateOrCreate([
        //     'exam_id' => $exam_id,
        //     'completed' => false
        // ],[

        // ]);

        $questions_right_answers = Exam::find($exam_id)
                ->questions()
                ->with(['options' => fn($qry) => $qry->where('value',1)])
                ->get()
                ->mapWithKeys(function($item){
                    $option_id = null;
                    if ($item->options != null && count($item->options)) {
                        $option_id = $item->options->first()->id;
                    }
                    return [$item->id => $option_id];
                });

        /**
         * Received Questions Correct Answers
         * {question_id: option_id(correct)}
         */
        // $questions_right_answers = DB::table('option_questions')
        //                             ->whereIn('question_id', $all_question_ids)
        //                             ->where('value',1)
        //                             ->pluck('id', 'question_id');
        
        $received_questions_answers = array_combine($received_questions, $received_answers);
        
        $temp = [];
        // return $questions_right_answers;
        // return [$questions_right_answers,$received_questions_answers];
        foreach ($questions_right_answers as $question_id =>$option_id) {
            $is_correct_value = null;
            if (array_key_exists($question_id, $received_questions_answers)) {
                $is_correct_value = $received_questions_answers[$question_id] == $option_id;
            }

            $data = [
                'student_exam_id' => $student_exam->id,
                'question_id' => $question_id,
                'selected_option_id' => is_bool($is_correct_value) ? $received_questions_answers[$question_id] : null,
                'is_correct' => $is_correct_value
            ];
            $temp[] = $data;
        }
        // return ($temp);
        $total_exam_questions = DB::table('questions')->Where('exam_id', $exam_id)->count();
        $is_user_exam_completed = false;

        // return collect($temp)->pluck('')
        // $student_exam->answers()->upsert($temp, ["student_exam_id","question_id"],['selected_option_id','is_correct']);
        // return 'ok';

        DB::transaction(function() use($student, $student_exam, $temp, $total_exam_questions, &$is_user_exam_completed){
            $student_exam->answers()->delete();
            $student_exam->answers()->createMany($temp);
            $student_exam->refresh();
            $student_exam->update(['completed' => true]);
            $is_user_exam_completed = true;
            // if ($student_exam->answers()->count() >= $total_exam_questions) {
            //     $student_exam->update(['completed' => true]);
            //     $is_user_exam_completed = true;
            // }
        });

        $scores = [
            'exam_id' => $exam_id,
            'total_question' => $total_exam_questions,
            'correct_answered' => $student_exam->answers()->where('is_correct', true)->count()
        ];
        return Response::apiSuccess('Answer Saved Successfully', $scores, 200);
        // if ($is_user_exam_completed) {
        //     $scores = [
        //         'exam_id' => $exam_id,
        //         'total_question' => $total_exam_questions,
        //         'correct_answered' => $student_exam->answers()->where('is_correct', true)->count()
        //     ];
        //     return Response::apiSuccess('All questions has been answered', $scores, 200);
        // }
        // return Response::apiSuccess('Answer Saved Successfully', null, 201);
    }


    /**
     * @OA\Get(
     *     path="/view-solutions/{exam_id}",
     *     summary="Fetch answers for a specific exam",
     *     description="Fetch all answers for the given exam for the authenticated student.",
     *     operationId="getResultsWithExam",
     *     tags={"Quiz"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="exam_id",
     *         in="path",
     *         required=true,
     *         description="The unique identifier of the exam",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved solutions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Solutions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="exam_id", type="integer"),
     *                     @OA\Property(property="student_id", type="integer"),
     *                     @OA\Property(property="answer", type="string"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No solutions found for this exam",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="No solutions found for this exam.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - The user is not authenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     */

    public function getResultsWithExam($exam_id)
    {
        $student_exam = Auth::guard('api')->user()->student_exams()->firstWhere('exam_id', $exam_id);
        if ($student_exam == null) {
            return Response::apiSuccess('This exam is not started', null, 403);
        }
        $questions = Exam::find($exam_id)
                        ->questions()
                        ->with([
                            'options'
                        ])
                        ->paginate(10);

        $pagination_data    = $questions->toArray();


        ['links' => $links] = $pagination_data;
        $data               = new QuestionCollection($questions);

        // $this_page_questions = $data->pluck('id');
        $user_choosed = StudentExam::where([['exam_id','=',$exam_id],['student_id','=',Auth::guard('api')->id()]])
                                ->first()
                                ->answers
                                ->pluck('selected_option_id','question_id');

        $resource_data_to_array = $data->resolve();
        $data = collect($resource_data_to_array)->map(function ($question) use($user_choosed) {
            $question['user_choosed'] = $user_choosed[$question['id']];
            return $question;
        });

        $links['current_page'] = $questions->currentPage();
        $links['last_page'] = $questions->lastPage();
        $links['total'] = $questions->total();

        $data    = compact('data', 'links');

        return Response::apiSuccess('User Exam Solutions', $data);

    }


    /**
     * @OA\Get(
     *     path="/solution/free-quiz",
     *     summary="Get Free Quizzes Done by the Student",
     *     description="Retrieve all free quizzes that have been completed by the authenticated student.",
     *     tags={"Solution"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Free quizzes retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Free quizzes retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="exam_id", type="integer", example=1),
     *                     @OA\Property(property="exam_name", type="string", example="Math Quiz"),
     *                     @OA\Property(property="status", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-26T12:00:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Something went wrong.")
     *         )
     *     )
     * )
     */
    public function getDoneFreeQuiz()
    {
        $student_id = Auth::id();
        $freeQuizzesGivenByStudent = AnswerSheet::where('student_id', $student_id)
            ->whereHas('exam', function ($query) {
                $query->where('status', 1);
            })
            ->with('exam')
            ->paginate(10);

        if ($freeQuizzesGivenByStudent->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No free quizzes found.',
                'data' => []
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Free quizzes retrieved successfully.',
            'data' => $freeQuizzesGivenByStudent
        ], 200);
    }


    /**
     * @OA\Get(
     *     path="/solution/sprint-quiz",
     *     summary="Get Sprint Quizzes Done by the Student",
     *     description="Retrieve all Sprint quizzes that have been completed by the authenticated student.",
     *     tags={"Solution"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sprint quizzes retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sprint quizzes retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="exam_id", type="integer", example=1),
     *                     @OA\Property(property="exam_name", type="string", example="Math Quiz"),
     *                     @OA\Property(property="status", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-26T12:00:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Something went wrong.")
     *         )
     *     )
     * )
     */

    public function getDoneSprintQuiz()
    {
        $student_id = Auth::id();
        $sprintQuizzesGivenByStudent = AnswerSheet::where('student_id', $student_id)
            ->whereHas('exam', function ($query) {
                $query->where('status', 3);
            })
            ->with('exam')
            ->paginate(10);
        if ($sprintQuizzesGivenByStudent->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No Sprint quizzes found.',
                'data' => []
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Sprint quizzes retrieved successfully.',
            'data' => $sprintQuizzesGivenByStudent
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/solution/mock-test",
     *     summary="Get Mock Tests Done by the Student",
     *     description="Retrieve all Mock Tests that have been completed by the authenticated student.",
     *     tags={"Solution"},
     * @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Mock Tests retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Mock Tests retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="exam_id", type="integer", example=1),
     *                     @OA\Property(property="exam_name", type="string", example="Math Quiz"),
     *                     @OA\Property(property="status", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-26T12:00:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Something went wrong.")
     *         )
     *     )
     * )
     */
    public function getDoneMockTest()
    {
        $student_id = Auth::id();
        $mockTestsGivenByStudent = AnswerSheet::where('student_id', $student_id)
            ->whereHas('exam', function ($query) {
                $query->where('status', 4);
            })
            ->with('exam')
            ->paginate(10);
        if ($mockTestsGivenByStudent->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No Mock Tests found.',
                'data' => []
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mock Tests retrieved successfully.',
            'data' => $mockTestsGivenByStudent
        ], 200);
    }
}
