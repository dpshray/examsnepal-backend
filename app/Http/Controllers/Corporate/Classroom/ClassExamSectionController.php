<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Corporate\Classroom\ClassExamSectionRequest;
use App\Http\Resources\Corporate\Classroom\ClassExamSectionResource;
use App\Models\Corporate\Classroom;
use App\Models\Corporate\ClassExamSection;
use App\Models\Exam;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class ClassExamSectionController extends Controller
{
    public function index(Classroom $class, Exam $exam)
    {
        $this->authorize($class, $exam);

        $sections = $exam->classExamSections()->withCount('questions')->get();

        return Response::apiSuccess('Exam sections', ClassExamSectionResource::collection($sections));
    }

    public function store(ClassExamSectionRequest $request, Classroom $class, Exam $exam)
    {
        $this->authorize($class, $exam);

        $data = $request->validated();
        $data['order'] = ((int) $exam->classExamSections()->max('order')) + 1;

        $section = $exam->classExamSections()->create($data);

        return Response::apiSuccess('Section added successfully', new ClassExamSectionResource($section));
    }

    public function update(ClassExamSectionRequest $request, Classroom $class, Exam $exam, ClassExamSection $section)
    {
        $this->authorize($class, $exam);
        $this->authorizeSectionBelongsToExam($exam, $section);

        $section->update($request->validated());

        return Response::apiSuccess('Section updated successfully', new ClassExamSectionResource($section));
    }

    public function destroy(Classroom $class, Exam $exam, ClassExamSection $section)
    {
        $this->authorize($class, $exam);
        $this->authorizeSectionBelongsToExam($exam, $section);

        $section->delete();

        return Response::apiSuccess('Section deleted successfully');
    }

    private function authorize(Classroom $class, Exam $exam): void
    {
        if ($class->institute_id !== Auth::user()->id) {
            throw new AuthorizationException('You do not have access to this class.');
        }

        if (!$class->exams()->where('exams.id', $exam->id)->exists()) {
            throw new AuthorizationException('This exam does not belong to this class.');
        }
    }

    private function authorizeSectionBelongsToExam(Exam $exam, ClassExamSection $section): void
    {
        if ($section->exam_id !== $exam->id) {
            throw new AuthorizationException('This section does not belong to this exam.');
        }
    }
}
