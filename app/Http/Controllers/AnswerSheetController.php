<?php

namespace App\Http\Controllers;

use App\Enums\ExamTypeEnum;
use App\Http\Requests\AnswerStoreRequest;
use App\Http\Resources\QuestionCollection;
use Illuminate\Http\Request;
use App\Models\Answersheet;
use App\Models\Exam;
use App\Models\StudentExam;
use App\Services\ScoreService;
use App\Traits\PaginatorTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

class AnswerSheetController extends Controller
{
    use PaginatorTrait;

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
     *             required={"exam_id","question_id","answer_id","is_exam_completed"},
     *             @OA\Property(
     *                 property="exam_id",
     *                 type="integer",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="is_exam_completed",
     *                 type="integer",
     *                 example=0
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
            'is_exam_completed' => 'required|between:0,1'
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
        // Log::info($validatedData);
        DB::transaction(function() use($student_exam, $temp, $validatedData){
            $student_exam->answers()->upsert($temp, ['student_exam_id', 'question_id'], ['selected_option_id', 'is_correct']);
            $student_exam->update(['is_exam_completed' => $validatedData['is_exam_completed']]);
        });

        $student_exam->refresh();
        $student_exam->load(['answers', 'exam.questions'])
            ->loadCount([
            'answers as correct_answer_count' => fn($q) => $q->where('is_correct', 1),
            'answers as incorrect_answer_count' => fn($q) => $q->where('is_correct', 0),
            'answers as missed_answer_count' => fn($q) => $q->where('is_correct', null),
            ]);
        $scores = (new ScoreService())->fetchExamScore($student_exam);
        $scores['is_exam_completed'] = (bool)$validatedData['is_exam_completed'];
        return Response::apiSuccess('Exam completed successfully.', $scores, 200);
    }

    /**
     * @OA\Get(
     *     path="/view-solutions/{exam_id}",
     *     summary="Fetch answers for a specific exam",
     *     description="Fetch all answers for the given exam for the authenticated student.",
     *     operationId="getResultsWithExam",
     *     tags={"Quiz"},
     *     @OA\Parameter(
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
     *         description="User Exam Solutions",
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
     *                         @OA\Property(property="id", type="integer", example=188718),
     *                         @OA\Property(property="exam_id", type="integer", example=2443),
     *                         @OA\Property(property="question", type="string", example="Which electrolyte disturbance is most commonly associated with chronic kidney disease?"),
     *                         @OA\Property(property="explanation", type="string", example="CKD impairs renal potassium excretion, leading to hyperkalemia, which can cause arrhythmias."),
     *                         @OA\Property(
     *                             property="options",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=3273733),
     *                                 @OA\Property(property="question_id", type="integer", example=188718),
     *                                 @OA\Property(property="option", type="string", example="Hypernatremia"),
     *                                 @OA\Property(property="value", type="integer", example=0)
     *                             )
     *                         ),
     *                         @OA\Property(property="user_choosed", type="integer", example=3273734)
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="total", type="integer", example=30)
     *             ),
     *             @OA\Property(property="message", type="string", example="User Exam Solutions")
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
            return Response::apiSuccess('User exam not found', null, 403);
        }

        $questions = $exam->questions()->with('options')->paginate();
        $data = $this->setupPagination($questions, QuestionCollection::class)->data;

        $user_choosed = $student_exam->answers->pluck('selected_option_id','question_id');

        $items = ($data['data'])->toArray(request());
        foreach ($items as $key => $value) {
            $items[$key]['user_choosed'] = $user_choosed->has($items[$key]['id']) ? $user_choosed[$items[$key]['id']] : null;
        }
        $data['data'] = $items;
        return Response::apiSuccess('User Exam Solutions', $data);

    }
}
