<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Enums\ExamTypeEnum;
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
            ->where('is_class_exam', false)
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

    /**
     * Create a brand-new exam directly within this class and attach it.
     * Marked is_class_exam so it never shows in the general Student Exams
     * list — it belongs to this class's enrolled students only, and its
     * questions are managed from Class Management, not Student Exams.
     */
    public function createNew(Request $request, Classroom $class)
    {
        $this->authorizeOwner($class);

        // Optional fields arrive as "" (not omitted) from the form when unused
        // (e.g. schedule fields while in "open" mode) — nullable rules only
        // skip on an actual null, so normalize empty strings first.
        foreach (['exam_date', 'exam_time', 'end_time', 'negative_marking_point', 'instructions'] as $key) {
            if ($request->input($key) === '') {
                $request->merge([$key => null]);
            }
        }

        $data = $request->validate([
            'exam_name' => 'required|string|max:255',
            'exam_mode' => 'required|in:open,scheduled',
            'exam_date' => 'required_if:exam_mode,scheduled|nullable|date_format:Y-m-d',
            'exam_time' => 'required_if:exam_mode,scheduled|nullable|date_format:H:i',
            'end_time' => 'required_if:exam_mode,scheduled|nullable|date_format:H:i|after:exam_time',
            'instructions' => 'nullable|string|max:5000',
            'is_shuffled_question' => 'required|boolean',
            'is_shuffled_option' => 'required|boolean',
            'is_negative_marking' => 'required|boolean',
            'negative_marking_point' => 'required_if:is_negative_marking,1|nullable|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $isScheduled = $data['exam_mode'] === 'scheduled';

        // createQuietly: skip ExamObserver's FCM broadcast (built for the
        // public Student Exams flow) since this exam has no exam_type_id
        // and is scoped to one class's enrolled students, not app-wide.
        $exam = Auth::user()->teacherExams()->createQuietly([
            'exam_name' => $data['exam_name'],
            'exam_mode' => $data['exam_mode'],
            'is_class_exam' => true,
            'exam_date' => $isScheduled ? $data['exam_date'] : null,
            'exam_time' => $isScheduled ? $data['exam_time'] : null,
            'end_time' => $isScheduled ? $data['end_time'] : null,
            'instructions' => $data['instructions'] ?? null,
            'is_shuffled_question' => $data['is_shuffled_question'],
            'is_shuffled_option' => $data['is_shuffled_option'],
            'is_negative_marking' => $data['is_negative_marking'],
            'negative_marking_point' => $data['is_negative_marking'] ? $data['negative_marking_point'] : 0,
            'status' => ExamTypeEnum::MOCK_TEST->value,
            'is_active' => $data['is_active'],
            'live' => 1,
            'assign' => 0,
            'points_per_question' => 1,
        ]);

        $class->exams()->syncWithoutDetaching([$exam->id]);

        return Response::apiSuccess('Exam created and linked to class successfully', new TeacherExamResource($exam));
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
