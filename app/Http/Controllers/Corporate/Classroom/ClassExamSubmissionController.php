<?php

namespace App\Http\Controllers\Corporate\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Corporate\ClassExamAnswer;
use App\Models\Corporate\ClassExamQuestion;
use App\Models\Corporate\Classroom;
use App\Models\Exam;
use App\Models\StudentExam;
use App\Services\ClassExamScoreService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ClassExamSubmissionController extends Controller
{
    public function index(Classroom $class, Exam $exam)
    {
        $this->authorize($class, $exam);

        $attempts = StudentExam::where('exam_id', $exam->id)
            ->with('institute_student:id,name,email')
            ->get();

        $scoreService = new ClassExamScoreService();

        $data = $attempts->map(function (StudentExam $attempt) use ($scoreService) {
            return [
                'id' => $attempt->id,
                'student' => [
                    'id' => $attempt->institute_student?->id,
                    'name' => $attempt->institute_student?->name,
                    'email' => $attempt->institute_student?->email,
                ],
                'is_exam_completed' => (bool) $attempt->is_exam_completed,
                'score' => $attempt->is_exam_completed ? $scoreService->compute($attempt) : null,
            ];
        });

        return Response::apiSuccess('Submissions', $data);
    }

    public function show(Classroom $class, Exam $exam, StudentExam $studentExam)
    {
        $this->authorize($class, $exam);
        $this->authorizeAttemptBelongsToExam($exam, $studentExam);

        $answers = $studentExam->classExamAnswers()->with('selectedOption')->get()->keyBy('class_exam_question_id');

        $sections = $exam->classExamSections()->with('questions.options')->get()->map(function ($section) use ($answers) {
            return [
                'id' => $section->id,
                'title' => $section->title,
                'questions' => $section->questions->map(function (ClassExamQuestion $question) use ($answers) {
                    $answer = $answers->get($question->id);

                    return [
                        'id' => $question->id,
                        'question_type' => $question->question_type,
                        'question' => $question->question,
                        'full_marks' => (float) $question->full_marks,
                        'options' => $question->question_type === 'mcq'
                            ? $question->options->map(fn ($o) => ['id' => $o->id, 'option' => $o->option, 'is_correct' => (bool) $o->value])
                            : null,
                        'answer_id' => $answer?->id,
                        'selected_option_id' => $answer?->class_exam_question_option_id,
                        'answer_text' => $answer?->answer_text,
                        'answer_file_url' => $answer?->answer_file_path ? Storage::disk('public')->url($answer->answer_file_path) : null,
                        'is_correct' => $answer?->is_correct,
                        'marks_obtained' => $answer?->marks_obtained !== null ? (float) $answer->marks_obtained : null,
                        'graded_at' => $answer?->graded_at,
                    ];
                }),
            ];
        });

        return Response::apiSuccess('Submission detail', [
            'student' => [
                'id' => $studentExam->institute_student?->id,
                'name' => $studentExam->institute_student?->name,
                'email' => $studentExam->institute_student?->email,
            ],
            'is_exam_completed' => (bool) $studentExam->is_exam_completed,
            'score' => $studentExam->is_exam_completed ? (new ClassExamScoreService())->compute($studentExam) : null,
            'sections' => $sections,
        ]);
    }

    public function gradeAnswer(Request $request, Classroom $class, Exam $exam, ClassExamAnswer $answer)
    {
        $this->authorize($class, $exam);
        $this->authorizeAnswerBelongsToExam($exam, $answer);

        $data = $request->validate([
            'marks_obtained' => 'required|numeric|min:0|max:' . (float) $answer->question->full_marks,
        ]);

        $answer->update([
            'marks_obtained' => $data['marks_obtained'],
            'graded_at' => now(),
            'graded_by' => Auth::user()->id,
        ]);

        return Response::apiSuccess('Answer graded successfully', [
            'answer_id' => $answer->id,
            'marks_obtained' => (float) $answer->marks_obtained,
        ]);
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

    private function authorizeAttemptBelongsToExam(Exam $exam, StudentExam $studentExam): void
    {
        if ($studentExam->exam_id !== $exam->id) {
            throw new AuthorizationException('This submission does not belong to this exam.');
        }
    }

    private function authorizeAnswerBelongsToExam(Exam $exam, ClassExamAnswer $answer): void
    {
        if ($answer->question->section->exam_id !== $exam->id) {
            throw new AuthorizationException('This answer does not belong to this exam.');
        }
    }
}
