<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ExamTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Question\AdminStoreQuestionRequest;
use App\Models\Exam;
use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\Teacher\TeacherBulkPublishQuestionRequest;
use App\Http\Requests\Teacher\TeacherQuestionStoreRequest;
use App\Http\Requests\UpdateExamRequest;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\Teacher\TeacherExamQuestionResource;
use App\Http\Resources\Teacher\TeacherExamResource;
use App\Models\OptionQuestion;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Services\FCMService;
use App\Services\QuestionWordImportService;
use App\Support\DataUriImage;
use App\Traits\PaginatorTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpWord\Element\Image;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
                        ->with(['options.media', 'media'])
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
            $optionKeys = ['a', 'b', 'c', 'd'];
            $options = [
                ['option' => $request->option_a, 'value' => $request->option_a_is_true],
                ['option' => $request->option_b, 'value' => $request->option_b_is_true],
                ['option' => $request->option_c, 'value' => $request->option_c_is_true],
                ['option' => $request->option_d, 'value' => $request->option_d_is_true],
            ];
            $question = $request->only(['question', 'explanation','exam_type_id','added_by']);
            $question = $exam->questions()->create($question);
            $createdOptions = $question->options()->createMany($options);

            foreach ($optionKeys as $index => $key) {
                if ($request->hasFile("option_{$key}_image")) {
                    $createdOptions[$index]
                        ->addMedia($request->file("option_{$key}_image"))
                        ->toMediaCollection(OptionQuestion::OPTION_IMAGE);
                }
            }

            if ($request->hasFile('image')) {
                $question->addMedia($request->file('image'))->toMediaCollection(Question::QUESTION_IMAGE);
            }
            if ($request->hasFile('explanation_image')) {
                $question->addMedia($request->file('explanation_image'))->toMediaCollection(Question::EXPLANATION_IMAGE);
            }
        });
        return Response::apiSuccess("Question added of exam name: {$exam->exam_name}");
    }

    /**
     * Bulk-import questions for an exam from an uploaded .docx file.
     */
    /**
     * @OA\Post(
     *     path="/teacher/exam/{exam}/questions/bulk-import",
     *     summary="Bulk import questions from a Word document",
     *     description="Parses an uploaded .docx file for numbered questions (4 lettered options, one marked with a trailing '*' as correct, and an Explanation line), creating a Question per valid entry. Malformed questions are skipped and reported back instead of failing the whole upload.",
     *     operationId="teacher_question_bulk_import",
     *     tags={"TeacherQuestion"},
     *     @OA\Parameter(
     *         name="exam",
     *         in="path",
     *         required=true,
     *         description="exam id to import questions into",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(property="file", type="string", format="binary", description=".docx file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Import finished (possibly with per-question errors)",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="created_count", type="integer", example=18),
     *                 @OA\Property(property="failed_count", type="integer", example=2),
     *                 @OA\Property(
     *                     property="errors",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="question_number", type="integer", example=5),
     *                         @OA\Property(property="reason", type="string", example="No correct option marked (add \"*\" after the correct option)")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Bulk import finished")
     *         )
     *     )
     * )
     */
    /**
     * Parse an uploaded .docx into question DTOs for the admin to preview and
     * edit in the browser. Nothing is written to the database here - see
     * publishBulkImport() for the step that actually creates records.
     */
    public function bulkImport(Request $request, Exam $exam, QuestionWordImportService $importService)
    {
        $this->isExamOwner($exam);
        $request->validate([
            'file' => 'required|mimes:docx',
        ]);

        $parsed = $importService->parse($request->file('file')->getRealPath());

        return Response::apiSuccess('Parsed ' . count($parsed['valid']) . ' question(s)', [
            'valid' => array_map([$this, 'questionToPreview'], $parsed['valid']),
            'errors' => $parsed['errors'],
        ]);
    }

    /**
     * Create questions from the (possibly admin-edited) JSON produced by the
     * preview step above. This is the only step in the bulk-import flow that
     * writes to the database.
     */
    public function publishBulkImport(TeacherBulkPublishQuestionRequest $request, Exam $exam)
    {
        $this->isExamOwner($exam);

        $questions = $request->validated()['questions'];
        $errors = [];
        $createdCount = 0;
        $addedBy = Auth::guard('users')->id();

        foreach ($questions as $index => $q) {
            try {
                DB::transaction(function () use ($q, $exam, $addedBy) {
                    $question = $exam->questions()->create([
                        'question' => $q['text'],
                        'explanation' => $q['explanation'],
                        'exam_type_id' => $exam->exam_type_id,
                        'added_by' => $addedBy,
                    ]);

                    $createdOptions = $question->options()->createMany(array_map(
                        fn ($option) => [
                            'option' => $option['text'],
                            'value' => $option['is_correct'],
                        ],
                        $q['options']
                    ));

                    foreach ($q['options'] as $optIndex => $option) {
                        if (!empty($option['image_data_uri'])) {
                            $image = DataUriImage::decode($option['image_data_uri']);
                            $createdOptions[$optIndex]
                                ->addMediaFromString($image['binary'])
                                ->usingFileName('option_' . $optIndex . '.' . $image['extension'])
                                ->toMediaCollection(OptionQuestion::OPTION_IMAGE);
                        }
                    }

                    if (!empty($q['image_data_uri'])) {
                        $image = DataUriImage::decode($q['image_data_uri']);
                        $question->addMediaFromString($image['binary'])
                            ->usingFileName('question.' . $image['extension'])
                            ->toMediaCollection(Question::QUESTION_IMAGE);
                    }

                    if (!empty($q['explanation_image_data_uri'])) {
                        $image = DataUriImage::decode($q['explanation_image_data_uri']);
                        $question->addMediaFromString($image['binary'])
                            ->usingFileName('explanation.' . $image['extension'])
                            ->toMediaCollection(Question::EXPLANATION_IMAGE);
                    }
                });
                $createdCount++;
            } catch (Throwable $e) {
                $errors[] = [
                    'question_number' => $index + 1,
                    'reason' => 'Failed to save: ' . $e->getMessage(),
                ];
            }
        }

        return Response::apiSuccess('Bulk import finished', [
            'created_count' => $createdCount,
            'failed_count' => count($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * Convert a parsed question (which may hold raw PHPWord Image objects)
     * into a JSON-safe shape for the preview response.
     */
    private function questionToPreview(array $q): array
    {
        $q['image'] = $this->imageToPreview($q['image']);
        $q['explanation_image'] = $this->imageToPreview($q['explanation_image']);

        foreach ($q['options'] as $letter => $option) {
            $q['options'][$letter]['image'] = $this->imageToPreview($option['image']);
        }

        return $q;
    }

    private function imageToPreview(?Image $image): ?array
    {
        if ($image === null) {
            return null;
        }

        return [
            'data_uri' => 'data:' . $image->getImageType() . ';base64,' . $image->getImageStringData(true),
            'filename' => 'image.' . $image->getImageExtension(),
        ];
    }

    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/teacher/question/{question}",
     *     summary="Get detail of question.",
     *     description="Get detail of question.",
     *     operationId="teacher_exam_question_detail",
     *     tags={"TeacherQuestion"},
     *     @OA\Parameter(
     *         name="question",
     *         in="path",
     *         required=true,
     *         description="Question id of an exam",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question fetched successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=193158),
     *                 @OA\Property(property="question", type="string", example="Velit accusantium v"),
     *                 @OA\Property(property="explanation", type="string", example="Laboriosam velit e sadaffd"),
     *                 @OA\Property(
     *                     property="options",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3291781),
     *                         @OA\Property(property="question_id", type="integer", example=193158),
     *                         @OA\Property(property="option", type="string", example="Voluptas qui est eos"),
     *                         @OA\Property(property="value", type="integer", example=0)
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Question fetched successfully.")
     *         )
     *     )
     * )
     */
    public function show(Exam $exam, Question $question)
    {
        $this->isQuestionOwner($question);
        $question->load(['options.media', 'media']);
        $question = new TeacherExamQuestionResource($question);
        return Response::apiSuccess('Question fetched successfully.', $question);
    }

    /**
     * Update the specified resource in storage.
     */
     /**
     * @OA\POST(
     *     path="/teacher/question/{question}",
     *     summary="Update a question of an exam",
     *     description="Update an existing question (with options and image). Only the exam owner (teacher) can update.",
     *     operationId="teacher_exam_question_update",
     *     tags={"TeacherQuestion"},
     *     @OA\Parameter(
     *         name="question",
     *         in="path",
     *         required=true,
     *         description="ID of the question",
     *         @OA\Schema(type="integer", example=101)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"question","option_a","option_b","option_c","option_d"},
     *                 @OA\Property(property="question", type="string", example="What is the capital of Nepal?"),
     *                 @OA\Property(property="_method", type="string", example="patch"),
     *                 @OA\Property(property="explanation", type="string", example="Kathmandu is the capital city."),
     *                 @OA\Property(property="option_a_id", type="integer", example="12345"),
     *                 @OA\Property(property="option_b_id", type="integer", example="12345"),
     *                 @OA\Property(property="option_c_id", type="integer", example="12345"),
     *                 @OA\Property(property="option_d_id", type="integer", example="12345"),
     *                 @OA\Property(property="option_a", type="string", example="Kathmandu"),
     *                 @OA\Property(property="option_a_is_true", type="boolean", example=true),
     *                 @OA\Property(property="option_b", type="string", example="Pokhara"),
     *                 @OA\Property(property="option_b_is_true", type="boolean", example=false),
     *                 @OA\Property(property="option_c", type="string", example="Lalitpur"),
     *                 @OA\Property(property="option_c_is_true", type="boolean", example=false),
     *                 @OA\Property(property="option_d", type="string", example="Bhaktapur"),
     *                 @OA\Property(property="option_d_is_true", type="boolean", example=false),
     *                 @OA\Property(property="image", type="string", format="binary", description="Optional image for the question")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Question Updated Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="string", nullable=true, example=null),
     *             @OA\Property(property="message", type="string", example="Question updated for exam: Example Exam Name")
     *         )
     *     )
     * )
    */
    function update(AdminStoreQuestionRequest $request ,Question $question)
    {
        //
        $this->isQuestionOwner($question);
        $request_option_id_not_match_with_existing_question_option_id = $question->options
            ->pluck('id')
            ->diff($request->only([
                "option_d_id",
                "option_c_id",
                "option_b_id",
                "option_a_id",
            ]))
            ->isNotEmpty();
        if ($request_option_id_not_match_with_existing_question_option_id) {
            return Response::apiError("The selected option does not belong to this question.");
        }

        DB::transaction(function () use ($request, $question) {
            //update question
            $question->update([
                'question'    => $request->question ?? $question->question,
                'explanation' => $request->explanation ?? $question->explanation,
            ]);
            //update option
            // if ($request->has(['option_a', 'option_b', 'option_c', 'option_d'])) {
                // Remove old options
                // $question->options()->delete();
                // Recreate new options
                $options = [
                    ['key' => 'a', 'option_id' => $request->option_a_id, 'option' => $request->option_a, 'value' => $request->option_a_is_true],
                    ['key' => 'b', 'option_id' => $request->option_b_id, 'option' => $request->option_b, 'value' => $request->option_b_is_true],
                    ['key' => 'c', 'option_id' => $request->option_c_id, 'option' => $request->option_c, 'value' => $request->option_c_is_true],
                    ['key' => 'd', 'option_id' => $request->option_d_id, 'option' => $request->option_d, 'value' => $request->option_d_is_true],
                ];
                foreach ($options as $option) {
                    $question->options()->where('id', $option['option_id'])
                        ->update([
                            'option' => $option['option'],
                            'value' => $option['value']
                        ]);

                    if ($request->hasFile("option_{$option['key']}_image")) {
                        OptionQuestion::find($option['option_id'])
                            ->addMedia($request->file("option_{$option['key']}_image"))
                            ->toMediaCollection(OptionQuestion::OPTION_IMAGE);
                    }
                }
            // }
            //update image if exists
            if ($request->hasFile('image')) {
                $question->addMedia($request->file('image'))->toMediaCollection(Question::QUESTION_IMAGE);
            }
            if ($request->hasFile('explanation_image')) {
                $question->addMedia($request->file('explanation_image'))->toMediaCollection(Question::EXPLANATION_IMAGE);
            }
        });
        return Response::apiSuccess("Question has been updated.");
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
        throw_if(!Auth::user()->isAdmin() && $exam->user->isNot(Auth::guard('users')->user()), AuthorizationException::class, 'You are not the owner');
    }

    private function isQuestionOwner(Question $question){
        throw_if(!Auth::user()->isAdmin() && $question->exam->user->isNot(Auth::guard('users')->user()), AuthorizationException::class, 'You are not the owner');
    }
}
