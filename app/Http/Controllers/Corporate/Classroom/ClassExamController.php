<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Resources\Teacher\TeacherExamResource;
use App\Models\Corporate\Classroom;
use App\Models\Exam;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class ClassExamController extends Controller
{
    public function index(Classroom $class)
    {
        $this->authorizeOwner($class);

        $exams = $class->exams()->withCount('questions')->orderByDesc('exams.id')->get();

        return Response::apiSuccess('Exams linked to class', TeacherExamResource::collection($exams));
    }

    public function available(Classroom $class)
    {
        $this->authorizeOwner($class);

        $linkedIds = $class->exams()->pluck('exams.id');

        $exams = Exam::where('user_id', Auth::user()->id)
            ->whereNotIn('id', $linkedIds)
            ->withCount('questions')
            ->orderBy('exam_name')
            ->get();

        return Response::apiSuccess('Exams available to link', TeacherExamResource::collection($exams));
    }

    public function store(Request $request, Classroom $class)
    {
        $this->authorizeOwner($class);

        $data = $request->validate([
            'exam_id' => 'required|integer|exists:exams,id',
        ]);

        $exam = Exam::findOrFail($data['exam_id']);
        if ($exam->user_id !== Auth::user()->id) {
            throw new AuthorizationException('You do not have access to this exam.');
        }

        $class->exams()->syncWithoutDetaching([$exam->id]);

        return Response::apiSuccess('Exam linked to class successfully');
    }

    public function destroy(Classroom $class, Exam $exam)
    {
        $this->authorizeOwner($class);

        $class->exams()->detach($exam->id);

        return Response::apiSuccess('Exam unlinked from class successfully');
    }

    private function authorizeOwner(Classroom $class): void
    {
        if ($class->institute_id !== Auth::user()->id) {
            throw new AuthorizationException('You do not have access to this class.');
        }
    }
}
