<?php

namespace App\Http\Controllers\Corporate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Question\CorporateQuestionRequest;
use App\Models\Corporate\CorporateExamSection;
use App\Models\Corporate\CorporateQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class CorporateQuestionController extends Controller
{
    //
    function index(CorporateExamSection $section)
    {
        $this->checkOwnership($section);
        $questions = $section->questions;
        return  Response::apiSuccess('list of questions', $questions);
    }
    function store(CorporateQuestionRequest $request, CorporateExamSection $section)
    {
        $data=$request->validated();
        $this->checkOwnership($section);
        $question = $section->questions()->create($data);
        return Response::apiSuccess('question created successfully', $question);
    }
    function update(CorporateQuestionRequest $request, CorporateQuestion $question)
    {
        $data = $request->validated();
        $question->update($data);
        $this->checkOwnership($question->section);
        return Response::apiSuccess('question updated successfully', $question);
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
