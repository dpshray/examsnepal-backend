<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Question\CorporateQuestionRequest;
use App\Http\Resources\Corporate\CorporateQuestionCollection;
use App\Http\Resources\Corporate\CorporateQuestionResource;
use App\Models\Corporate\CorporateExamSection;
use App\Models\Corporate\CorporateQuestion;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class CorporateQuestionController extends Controller
{
    //
    use PaginatorTrait;
    /**
     * @OA\Get(
     *     path="/corporate/exam/section/{section_id}/questions",
     *     summary="Get list of questions for a corporate exam section",
     *     tags={"Corporate Exam Questions"},
     *      @OA\Parameter(
     *         name="section_id",
     *         in="path",
     *         required=true,
     *         description="Corporate exam section ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of questions per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=12)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="list of questions"),
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="section_id", type="integer", example=1),
     *                         @OA\Property(property="question_type", type="string", example="MCQ"),
     *                         @OA\Property(property="question", type="string", example="What is 2 + 2?"),
     *                         @OA\Property(property="description", type="string", example="Basic math question"),
     *                         @OA\Property(property="full_marks", type="integer", example=5),
     *                         @OA\Property(property="negative_marks", type="integer", example=1),
     *
     *                         @OA\Property(
     *                             property="options",
     *                             type="array",
     *                             nullable=true,
     *                             @OA\Items(
     *                                 @OA\Property(property="id", type="integer", example=10),
     *                                 @OA\Property(property="option", type="string", example="4"),
     *                                 @OA\Property(property="value", type="string", example="true"),
     *                             )
     *                         ),
     *
     *                         @OA\Property(property="created_at", type="string", example="2025-01-10 10:00:00"),
     *                         @OA\Property(property="updated_at", type="string", example="2025-01-10 10:00:00")
     *                     )
     *                 ),
     *
     *                 @OA\Property(
     *                     property="meta",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=10),
     *                     @OA\Property(property="path", type="string", example="http://api.example.com/corporate/exam/1/questions"),
     *                     @OA\Property(property="per_page", type="integer", example=12),
     *                     @OA\Property(property="to", type="integer", example=12),
     *                     @OA\Property(property="total", type="integer", example=120)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    function index(CorporateExamSection $section, Request $request)
    {
        $per_page = $request->query('per_page', 12);
        $this->checkOwnership($section);
        $questions = $section->questions()->with('options')->paginate($per_page);
        $data = $this->setupPagination($questions, CorporateQuestionCollection::class)->data;
        return  Response::apiSuccess('list of questions', $data);
    }
    /**
     * @OA\Get(
     *     path="/corporate/exam/section/{section_id}/question/{id}",
     *     summary="Show a single question",
     *     tags={"Corporate Exam Questions"},
     *      @OA\Parameter(
     *         name="section_id",
     *         in="path",
     *         required=true,
     *         description="Corporate exam section ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Question ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="question detail"),
     *
     *             @OA\Property(
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
     *                         @OA\Property(property="value", type="string", example="true")
     *                     )
     *                 ),
     *
     *                 @OA\Property(property="created_at", type="string", example="2025-01-10 10:00:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-01-10 10:00:00")
     *             )
     *         )
     *     )
     * )
     */

    function show(CorporateExamSection $section, CorporateQuestion $question)
    {
        $this->checkOwnership($question->section);
        $question = new CorporateQuestionResource($question);
        return Response::apiSuccess('question details', $question);
    }
    /**
     * @OA\Post(
     *     path="/corporate/exam/section/{section_id}/questions",
     *     summary="Create a new question for a corporate exam section",
     *     tags={"Corporate Exam Questions"},
     *
     *     @OA\Parameter(
     *         name="section_id",
     *         in="path",
     *         required=true,
     *         description="Corporate exam section ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"question", "is_negative_marking", "full_marks", "question_type"},
     *                 @OA\Property(property="question", type="string", example="What is 2 + 2?"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Basic math question"),
     *                 @OA\Property(property="is_negative_marking", type="boolean", example=true),
     *                 @OA\Property(property="negative_mark", type="number", example=1, description="Required if is_negative_marking = 1"),
     *                 @OA\Property(property="full_marks", type="number", example=5),
     *                 @OA\Property(property="question_type", type="string", enum={"MCQ","Subjective"}, example="MCQ"),
     *
     *                 @OA\Property(
     *                     property="options",
     *                     type="array",
     *                     description="Required only if question_type = MCQ",
     *                     @OA\Items(
     *                         @OA\Property(property="option", type="string", example="4"),
     *                         @OA\Property(property="value", type="boolean", example=true)
     *                     )
     *                 ),
     *
     *                 @OA\Property(
     *                     property="image",
     *                     type="string",
     *                     format="binary",
     *                     description="Optional question image"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Question created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="question created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=10),
     *                 @OA\Property(property="section_id", type="integer", example=1),
     *                 @OA\Property(property="question_type", type="string", example="MCQ"),
     *                 @OA\Property(property="question", type="string", example="What is 2 + 2?"),
     *                 @OA\Property(property="description", type="string", example="Basic math"),
     *                 @OA\Property(property="full_marks", type="number", example=5),
     *                 @OA\Property(property="is_negative_marking", type="boolean", example=true),
     *                 @OA\Property(property="negative_mark", type="number", example=1),
     *
     *                 @OA\Property(
     *                     property="options",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=101),
     *                         @OA\Property(property="option", type="string", example="4"),
     *                         @OA\Property(property="value", type="boolean", example=true)
     *                     )
     *                 ),
     *
     *                 @OA\Property(property="image_url", type="string", example="https://example.com/media/question.jpg"),
     *                 @OA\Property(property="created_at", type="string", example="2025-01-10 10:00:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-01-10 10:00:00")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */

    function store(CorporateQuestionRequest $request, CorporateExamSection $section)
    {
        $data = $request->validated();
        $this->checkOwnership($section);
        DB::beginTransaction();
        try {
            $question = $section->questions()->create($data);
            if ($request->hasFile('image')) {
                $question->addMediaFromRequest('image')->toMediaCollection(CorporateQuestion::QUESTION_IMAGE);
            }
            if ($question->question_type === 'mcq' || $question->question_type === 'MCQ') {
                $options = $data['options'] ?? [];
                foreach ($options as $optionData) {
                    $question->options()->create($optionData);
                }
            }
            DB::commit();
            return Response::apiSuccess('question created successfully', $question);
        } catch (\Exception $e) {
            Log::error('Failed to create question: ' . $e->getMessage());
            DB::rollBack();
            return Response::apiError('Failed to create question', 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/corporate/exam/section/{section_id}/questions/{id}",
     *     summary="Update an existing question",
     *     tags={"Corporate Exam Questions"},
     *
     *     @OA\Parameter(
     *         name="section_id",
     *         in="path",
     *         required=true,
     *         description="Corporate exam section ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Question ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="question", type="string", example="Updated question text"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Updated description"),
     *                 @OA\Property(property="is_negative_marking", type="boolean", example=true),
     *                 @OA\Property(property="negative_mark", type="number", example=1),
     *                 @OA\Property(property="full_marks", type="number", example=10),
     *                 @OA\Property(property="question_type", type="string", enum={"MCQ","Subjective"}, example="MCQ"),
     *                 @OA\Property(property="_method", type="string", example="PATCH"),
     *                 @OA\Property(
     *                     property="options",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="option", type="string", example="Option A"),
     *                         @OA\Property(property="value", type="boolean", example=false)
     *                     )
     *                 ),
     *
     *                 @OA\Property(
     *                     property="image",
     *                     type="string",
     *                     format="binary",
     *                     description="Optional image to replace old one"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Question updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="question updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=4),
     *                 @OA\Property(property="section_id", type="integer", example=1),
     *                 @OA\Property(property="question_type", type="string", example="MCQ"),
     *                 @OA\Property(property="question", type="string", example="Updated question text"),
     *                 @OA\Property(property="description", type="string", example="Updated description"),
     *                 @OA\Property(property="full_marks", type="number", example=10),
     *                 @OA\Property(property="is_negative_marking", type="boolean", example=true),
     *                 @OA\Property(property="negative_mark", type="number", example=1),
     *                 @OA\Property(
     *                     property="options",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=12),
     *                         @OA\Property(property="option", type="string", example="Updated option"),
     *                         @OA\Property(property="value", type="boolean", example=true)
     *                     )
     *                 ),
     *
     *                 @OA\Property(property="image_url", type="string", example="https://example.com/media/new_image.jpg"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-01-11 14:00:00")
     *             )
     *         )
     *     )
     * )
     */

    function update(CorporateQuestionRequest $request,CorporateExamSection $section, CorporateQuestion $question)
    {
        $data = $request->validated();
        $this->checkOwnership($question->section);
        DB::beginTransaction();
        try {
            $question->update($data);
            if ($request->hasFile('image')) {
                $question->clearMediaCollection(CorporateQuestion::QUESTION_IMAGE);
                $question->addMediaFromRequest('image')->toMediaCollection(CorporateQuestion::QUESTION_IMAGE);
            }
            if ($question->question_type === 'mcq') {
                $question->options()->delete();
                $options = $data['options'] ?? [];
                foreach ($options as $optionData) {
                    $question->options()->create($optionData);
                }
            }
            DB::commit();
            return Response::apiSuccess('question updated successfully', $question);
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::apiError('Failed to update question', 500);
        }
    }
    /**
     * @OA\Delete(
     *     path="/corporate/exam/section/{section_id}/questions/{id}",
     *     summary="Delete a question",
     *     tags={"Corporate Exam Questions"},
     *
     *     @OA\Parameter(
     *         name="section_id",
     *         in="path",
     *         required=true,
     *         description="Corporate exam section ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Question ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Question deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="question deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Question not found"
     *     )
     * )
     */

    function destroy(CorporateExamSection $section, CorporateQuestion $question)
    {
        $this->checkOwnership($question->section);
        $question->delete();
        return Response::apiSuccess('question deleted successfully');
    }
    private function checkOwnership(CorporateExamSection $section)
    {
        $user=Auth::user();
        Log::info('Authenticated user ID: ' . $user->id);
        Log::info('Exam corporate ID: ' . $section->exam->corporate_id);
        if ($user->id !== $section->exam->corporate_id) {
            abort(403, 'Unauthorized: You do not own this exam.');
        }
    }
}
