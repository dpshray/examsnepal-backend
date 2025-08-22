<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ExamTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\Teacher\TeacherQuestionStoreRequest;
use App\Http\Requests\UpdateExamRequest;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\Teacher\TeacherExamQuestionResource;
use App\Http\Resources\Teacher\TeacherExamResource;
use App\Models\Question;
use App\Traits\PaginatorTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class TeacherQuestionController extends Controller
{
    use PaginatorTrait; 
    /**
     * Display a listing of the resource.
     */
    /**
     * @OA\Get(
     *     path="/teacher/exam/{exam}/question",
     *     summary="Get all question of an exam of logged in teacher",
     *     description="Fetches all question of an exam of logged in teacher",
     *     operationId="teacher_exam_question_list",
     *     tags={"TeacherQuestion"},
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="ID of an exam",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="page no of list",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="items per page",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exam questions list with options",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="questions",
     *                     type="object",
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=185959),
     *                             @OA\Property(property="question", type="string", example="What is the highest mountain peak in the world?"),
     *                             @OA\Property(
     *                                 property="explanation",
     *                                 type="string",
     *                                 example="The correct answer, Mount Everest, is considered the highest mountain peak..."
     *                             ),
     *                             @OA\Property(
     *                                 property="options",
     *                                 type="array",
     *                                 @OA\Items(
     *                                     type="object",
     *                                     @OA\Property(property="id", type="integer", example=3262633),
     *                                     @OA\Property(property="question_id", type="integer", example=185959),
     *                                     @OA\Property(property="option", type="string", example="The Mount Everest"),
     *                                     @OA\Property(property="value", type="integer", example=1)
     *                                 )
     *                             )
     *                         )
     *                     ),
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="total", type="integer", example=10)
     *                 ),
     *                 @OA\Property(
     *                     property="exam",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2378),
     *                     @OA\Property(property="is_active", type="integer", example=1),
     *                     @OA\Property(property="exam_name", type="string", example="This is some exam name"),
     *                     @OA\Property(property="exam_date", type="string", nullable=true, example=null),
     *                     @OA\Property(property="exam_time", type="string", nullable=true, example=null),
     *                     @OA\Property(property="end_time", type="string", nullable=true, example=null),
     *                     @OA\Property(property="category", type="string", example="MOCK_TEST"),
     *                     @OA\Property(property="description", type="string", example="This is some description for this exam"),
     *                     @OA\Property(
     *                         property="exam_type",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="MBBS Entrance Exams")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="exam name: This is some exam name, question list")
     *         )
     *     )
     * )
     */
    public function index(Request $request, Exam $exam)
    {
        $this->isExamOwner($exam);
        $per_page = $request->query('per_page',10);
        $exam_title = $exam->exam_name;
        $pagination = $exam->questions()
                        ->with(['options'])
                        ->orderBy('id', 'DESC')
                        ->paginate($per_page);
        $questions = $this->setupPagination($pagination, fn($item) => TeacherExamQuestionResource::collection($item))->data;
        // $questions = $pagination;
        $exam->loadMissing(['examType:id,name']);
        $exam = [
            "id" => $exam->id,
            "is_active" => $exam->is_active,
            "exam_name" => $exam->exam_name,
            "exam_date" => $exam->exam_date,
            "exam_time" => $exam->exam_time,
            "end_time" => $exam->end_time,
            "category" => ExamTypeEnum::getKeyByValue($exam->status),
            "description" => $exam->description,
            "exam_type" => $exam->examType
        ];
        return Response::apiSuccess("exam name: {$exam_title}, question list", compact('questions','exam'));
    }

    /**
     * Store a newly created resource in storage.
     */
    /**
     * @OA\Post(
     *     path="/teacher/exam/{exam}/question",
     *     summary="Store question (with options) for an exam of a teacher",
     *     description="Stores a question along with multiple-choice options and an optional image for a teacher's exam.",
     *     operationId="teacher_question_store",
     *     tags={"TeacherQuestion"},
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="exam id of question",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={
     *                     "question",
     *                     "option_a",
     *                     "option_a_is_true",
     *                     "option_b",
     *                     "option_b_is_true",
     *                     "option_c",
     *                     "option_c_is_true",
     *                     "option_d",
     *                     "option_d_is_true",
     *                     "explanation"
     *                 },
     *                 @OA\Property(property="question", type="string", example="Who was the first person to land on the moon?"),
     *                 @OA\Property(property="option_a", type="string", example="Albert Einstein"),
     *                 @OA\Property(property="option_a_is_true", type="integer", example=0),
     *                 @OA\Property(property="option_b", type="string", example="Edmund Hillary"),
     *                 @OA\Property(property="option_b_is_true", type="integer", example=0),
     *                 @OA\Property(property="option_c", type="string", example="Neil Armstrong"),
     *                 @OA\Property(property="option_c_is_true", type="integer", example=1),
     *                 @OA\Property(property="option_d", type="string", example="Bill Clinton"),
     *                 @OA\Property(property="option_d_is_true", type="integer", example=0),
     *                 @OA\Property(property="explanation", type="string", example="On July 20, 1969, during NASA’s Apollo 11 mission, Neil Armstrong became the first human to step onto the Moon."),
     *                 @OA\Property(
     *                     property="image",
     *                     type="string",
     *                     format="binary",
     *                     description="Optional image file to upload"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question Added Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="Question added of exam name: This is some exam name")
     *         )
     *     )
     * )
     */
    public function store(TeacherQuestionStoreRequest $request, Exam $exam)
    {
        $this->isExamOwner($exam);
        $request->merge([
            'exam_type_id' => $exam->exam_type_id,
            'added_by' => Auth::guard('users')->id()
        ]);
        DB::transaction(function () use($request,$exam){
            $options = [
                ['option' => $request->option_a, 'value' => $request->option_a_is_true],
                ['option' => $request->option_b, 'value' => $request->option_b_is_true],
                ['option' => $request->option_c, 'value' => $request->option_c_is_true],
                ['option' => $request->option_d, 'value' => $request->option_d_is_true],
            ];
            $question = $request->only(['question', 'explanation','exam_type_id','added_by']);
            $question = $exam->questions()->create($question);
            clone($question)->options()->createMany($options);
            
            if ($request->hasFile('image')) {
                $image_dir_name = $question->id;
                $image_ext = $request->image->getClientOriginalExtension();
                $image_name = 'question-image-' . $image_dir_name . '.'. $image_ext;
                $question->image()->create(['image' => $image_name]);
                Storage::disk('exam')->putFileAs($image_dir_name, $request->image, $image_name);                
            }
        });
        return Response::apiSuccess("Question added of exam name: {$exam->exam_name}");
    }

    /**
     * Display the specified resource.
     */
    public function show(Exam $exam)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exam $exam)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/teacher/question/{question}",
     *     summary="Delete an exam's question",
     *     description="Delete an exam's question by its ID.",
     *     operationId="deleteTeacherExamQuestion",
     *     tags={"TeacherQuestion"},
     *     @OA\Parameter(
     *         name="question",
     *         in="path",
     *         description="The ID of the exam's question to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="question removed")
     *         )
     *     )
     * )
     */

    public function destroy(Question $question)
    {
        $this->isQuestionOwner($question);
        $question->delete();
        return Response::apiSuccess('question removed');
    }

    private function isExamOwner(Exam $exam){
        throw_if($exam->user->isNot(Auth::guard('users')->user()), AuthorizationException::class, 'You are not the owner');
    }
    private function isQuestionOwner(Question $question){
        throw_if($question->exam->user->isNot(Auth::guard('users')->user()), AuthorizationException::class, 'You are not the owner');
    }
}
