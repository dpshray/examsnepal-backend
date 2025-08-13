<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\Teacher\TeacherQuestionStoreRequest;
use App\Http\Requests\UpdateExamRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class TeacherQuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
     *     tags={"TeacherQuestionStore"},
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
        $this->isOwner($exam);
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
    public function destroy(Exam $exam)
    {
        //
    }

    private function isOwner(Exam $exam){
        throw_if($exam->user->isNot(Auth::guard('users')->user()), AuthorizationException::class, 'You are not the owner');
    }
}
