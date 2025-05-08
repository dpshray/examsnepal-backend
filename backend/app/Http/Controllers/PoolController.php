<?php

namespace App\Http\Controllers;

use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Models\StudentPool;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class PoolController extends Controller
{
    use PaginatorTrait;
    /**
     * @OA\Get(
     *     path="/request-pool-question",
     *     summary="Fetch a pool question",
     *     description="Fetch a pool question for user based on users exam id.",
     *     operationId="requestPoolQuestion",
     *     tags={"Pool"},
     *     @OA\Parameter(
     *         name="token",
     *         in="query",
     *         required=false,
     *         description="pool question token",
     *         @OA\Schema(
     *             type="string",
     *             example="Fn4nT34n"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pool question retrieved",
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
    public function getPoolQuestions(Request $request){
        $student = Auth::guard('api')->user();
        $today = now()->today()->format('Y-m-d');
        $student_pool = $student->student_pools()->whereDate('played_at', $today)->first();
        $questions_to_ignore = [];
        if ($student_pool) {
            if ($student_pool->strike == 3 || $student_pool->token != $request->query('token')) {
                return Response::apiError('Only one pool per day is allowed',null,400);
            } else {
                $questions_to_ignore = $student_pool->pools->pluck('question_id')->all();
            }
        }
        $exam_type_id = $student->exam_type_id;
        $data = Question::with('options')
                ->whereRelation('exam','exam_type_id', $exam_type_id)
                ->whereNotIn('id', $questions_to_ignore)
                ->inRandomOrder()
                ->first();
        $data = new QuestionResource($data);
        return Response::apiSuccess('Pool question', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/send-pool-response",
     *     operationId="sendPoolResponse",
     *     tags={"Pool"},
     *     summary="Send pool question response",
     *     description="Stores answers submitted by a student for a particular exam.",
     * 
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"question_id", "option_id"},
     *             @OA\Property(
     *                 property="question_id",
     *                 type="integer",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="option_id",
     *                 type="integer",
     *                 example=1
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
    public function sendPoolQuestionResponse(Request $request){
        $VD = $request->validate([
            'question_id' => 'required|exists:questions,id',
            'option_id' => 'required|exists:option_questions,id'
        ]);
        $student = Auth::guard('api')->user();
        $today = now()->today()->format('Y-m-d');
        $student_pool = $student->student_pools()->whereDate('played_at', $today)->first();
        if ($student_pool && $student_pool->strike >= 3) {
            return Response::apiError('Only one pool per day is allowed', null, 400);
        }
        $options = Question::with('options')
                    ->find($VD['question_id'])
                    ->options
                    ->pluck('value','id')
                    ->all();
        if (!array_key_exists($VD['option_id'], $options)) {
            return Response::apiError('this option is does not belongs to the question.',null,400);
        }
        $token = str()->random(25);
        $todays_student_pool = $student->student_pools()->updateOrCreate(
            ['student_id' => $student->id, 'played_at' => $today],
            ['token' => $token]
        );
        $has_pools = $todays_student_pool->pools;
        if ($has_pools) {
            $pool_questions_asked = $has_pools->pluck('question_id')->all();
            if (in_array($request->question_id, $pool_questions_asked)) {
                $token = compact('token');
                return Response::apiError('This pool question has already been answered', $token, 400);
            }
        }
        // else if ($todays_student_pool->strikes == 3) {
        //     return Response::apiError('All 3 strikes have been used', null, 400);
        // }

        $is_correct = $options[$VD['option_id']] == 1;

        if ($is_correct) {
            $todays_student_pool->pools()->create([
                'question_id' => $request->question_id,
                'option_id' => $request->option_id,
                'is_correct' => true
            ]);
            $todays_student_pool->refresh();
            return Response::apiSuccess('Correct answer',['type' => 1, 'strike' => $todays_student_pool->strike, 'token' => $token]);
        }else{
            tap($todays_student_pool, function ($TSP) use ($request) {
                $TSP->increment('strike');

                $TSP->pools()->create([
                    'question_id' => $request->question_id,
                    'option_id'   => $request->option_id,
                    'is_correct'  => false,
                ]);
            });

            $todays_student_pool->refresh();
            $todays_student_pool_strikes = $todays_student_pool->strike;
            if ($todays_student_pool_strikes == 3) {
                $score = $todays_student_pool->pools->where('is_correct',1)->count();
                $strike = $todays_student_pool_strikes;
                return Response::apiError('Pool game is over',compact('score','strike'),400);
            }
            return Response::apiSuccess('Wrong answer',['type' => 0, 'strike' => $todays_student_pool_strikes, 'token' => $token]);
        }
    }

    /**
     * @OA\Get(
     *     path="/get-todays-pool-players",
     *     summary="Fetch todays pool players",
     *     description="Fetch list of pool players.",
     *     operationId="getTodaysPoolPlayers",
     *     tags={"Pool"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(
     *             type="integer",
     *             example="1"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pool question retrieved",
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
    public function fetchTodaysPoolPlayers() {
        $student = Auth::guard('api')->user();
        $today = now();
        $pool_players = StudentPool::select("id","student_id")
                            ->with(['student:id,name'])
                            ->withCount(['pools as score' => fn($qry) => $qry->where('is_correct',1)])
                            ->whereRelation('student','exam_type_id', $student->exam_type_id)
                            ->whereDate('played_at', $today)
                            ->orderBy('score','DESC')
                            ->paginate();
        $data = $this->setupPagination($pool_players)->data;
        $today_date_str = $today->format('d-m-Y');
        return Response::apiSuccess("List of todays pool players({$today_date_str})", $data);
    }
}
