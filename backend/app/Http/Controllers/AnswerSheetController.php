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
use Illuminate\Validation\ValidationException;

class AnswerSheetController extends Controller
{
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
            // 'question_ids' => 'required|array',
            // 'question_ids.*' => 'integer|exists:option_questions,id'
        ]);

        if ($request->question_id === null) {
            throw ValidationException::withMessages(['question_id' => 'Question id cannot be of type null']);
        }else if($request->option_id === null){
            throw ValidationException::withMessages(['option_id' => 'Option id cannot be of type null']);
        }

        $exam_id = $request->exam_id;
        $student = Auth::guard('api')->user();
        
        $received_questions = $validatedData['question_id'];
        $received_answers = $validatedData['option_id'];

        if (count($received_answers) != count($received_questions)) {
            return Response::apiError('No. of questions does not match with the No. of options.', null, 422);
        }

        $student_exam = $student->student_exams()->firstWhere('exam_id',$exam_id);
        if ($student_exam == null) {
            return Response::apiError('This exam has not been initialized properly', null, 422);
        }

        /**
         * this code is commented so that response must
         * contains expected question_id and option_id
         * based on received exam_id
         */

        $exam_question_option = Exam::select('id','exam_name')
                                ->with(['questions' => fn($qry) => $qry->select('id','exam_id')->with(['options' => fn($qry) => $qry->select('id','question_id','option','value')])])
                                ->firstWhere('id',$exam_id);
        $excepted_questions = $exam_question_option->questions->pluck('id')->all();
        $expected_options =  $exam_question_option->questions->flatMap(fn($item) => $item->options)->pluck('id')->all();
        
        if(!empty(array_diff($received_questions, $excepted_questions))){
            throw ValidationException::withMessages(['question_id' => 'question id does not exists within this exam question id.']);
        }else if(!empty(array_diff($received_answers, $expected_options))){
            throw ValidationException::withMessages(['option_id' => 'option id does not exists within this exam questions option id.']);
        }

        $received_questions_answers = array_combine($received_questions, $received_answers);
        
        $temp = [];
        $answersheets = $student_exam->answers->pluck('id','question_id');
        foreach ($exam_question_option->questions as $question) {
            if (array_key_exists($question->id, $received_questions_answers)) {
                $selected_option = $received_questions_answers[$question->id];
                $is_correct = $question->options->firstWhere('id', $selected_option)->value == 1 ? true : false;
                $temp[] = [
                    'id' => $answersheets[$question->id],
                    'student_exam_id' => $student_exam->id,
                    'question_id' => $question->id,
                    'selected_option_id' => $selected_option,
                    'is_correct' => $is_correct
                ];
            }
        }
        // return $temp;
        DB::transaction(fn() => $student_exam->answers()->upsert($temp,['student_exam_id','question_id'],['selected_option_id','is_correct']));

        $scores = [
            'exam_id' => $exam_id,
            'total_question' => $exam_question_option->questions->count(),
            'correct_answered' => $student_exam->answers()->where('is_correct', true)->count()
        ];
        return Response::apiSuccess('Answer Saved Successfully', $scores, 200);
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

    public function getResultsWithExam(Exam $exam_id)
    {
        $exam = $exam_id;
        $student_exam = Auth::guard('api')->user()->student_exams()->firstWhere('exam_id', $exam->id);
        if ($student_exam == null) {
            return Response::apiSuccess('This exam is not started', null, 403);
        }

        $questions = $exam->questions()->with('options')->paginate(10);

        $pagination_data    = $questions->toArray();

        ['links' => $links] = $pagination_data;
        $data               = new QuestionCollection($questions);

        $user_choosed = $student_exam->answers->pluck('selected_option_id','question_id');

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
}
