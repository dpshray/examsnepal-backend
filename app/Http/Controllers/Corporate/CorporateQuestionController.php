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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class CorporateQuestionController extends Controller
{
    //
    use PaginatorTrait;
    /**
     * Display a listing of the resource.
     * @OA\Get(
     *     path="/corporate/exam/{section_id}/questions",
     *    summary="Get list of questions for a corporate exam section",
     *    tags={"Corporate Exam Questions"},
     *   @OA\Parameter(
     *       name="per_page",
     *       in="query",
     *       description="Number of questions per page",
     *       required=false,
     *       @OA\Schema(type="integer", default=12)
     *   )
     * )
     */
    function index(CorporateExamSection $section, Request $request)
    {
        $per_page = $request->query('per_page', 12);
        $this->checkOwnership($section);
        $questions = $section->questions()->paginate($per_page);
        $data = $this->setupPagination($questions, CorporateQuestionCollection::class)->data;
        return  Response::apiSuccess('list of questions', $data);
    }
    function show(CorporateQuestion $question)
    {
        $this->checkOwnership($question->section);
        $question = new CorporateQuestionResource($question);
        return Response::apiSuccess('question details', $question);
    }
    function store(CorporateQuestionRequest $request, CorporateExamSection $section)
    {
        $data=$request->validated();
        $this->checkOwnership($section);
        DB::beginTransaction();
        try {
            $question = $section->questions()->create($data);
            if ($request->hasFile('image')) {
                $question->addMediaFromRequest('image')->toMediaCollection(CorporateQuestion::QUESTION_IMAGE);
            }
            if($question->type==='MCQ')
            {
                $options = $data['options'] ?? [];
                foreach ($options as $optionData) {
                    $question->options()->create($optionData);
                }
            }
            DB::commit();
            return Response::apiSuccess('question created successfully', $question);
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::apiError('Failed to create question', 500);
        }
    }
    function update(CorporateQuestionRequest $request, CorporateQuestion $question)
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
            if($question->type==='MCQ')
            {
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
    function destroy(CorporateQuestion $question)
    {
        $this->checkOwnership($question->section);
        $question->delete();
        return Response::apiSuccess('question deleted successfully');
    }
    private function checkOwnership(CorporateExamSection $section)
    {
        if (auth()->id() !== $section->exam->corporate_id) {
            abort(403, 'Unauthorized: You do not own this exam.');
        }
    }
}
