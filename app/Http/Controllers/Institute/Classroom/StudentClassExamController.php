<?php

namespace App\Http\Controllers\Institute\Classroom;

use App\Exceptions\ClientStudentExamException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Institute\Classroom\StudentClassExamResource;
use App\Http\Resources\QuestionCollection;
use App\Models\Corporate\Classroom;
use App\Models\Corporate\ClassExamAnswer;
use App\Models\Corporate\ClassExamQuestion;
use App\Models\Exam;
use App\Models\StudentExam;
use App\Services\ClassExamScoreService;
use App\Services\ScoreService;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StudentClassExamController extends Controller
{
    use PaginatorTrait;

    public function index(string $slug)
    {
        $student = Auth::guard('institute_student')->user();
        $class = $this->resolveEnrolledClass($slug);

        $exams = $class->exams()
            ->where('exams.is_active', 1)
            ->withCount(['questions', 'classExamQuestions'])
            ->orderByDesc('exams.id')
            ->get();

        $flatAttempts = StudentExam::where('institute_student_id', $student->id)
            ->whereIn('exam_id', $exams->where('is_class_exam', false)->pluck('id'))
            ->withCount([
                'answers as correct_answer_count' => fn ($q) => $q->where('is_correct', 1),
                'answers as incorrect_answer_count' => fn ($q) => $q->where('is_correct', 0),
                'answers as missed_answer_count' => fn ($q) => $q->where('is_correct', null),
            ])
            ->get()
            ->keyBy('exam_id');

        $sectionedAttempts = StudentExam::where('institute_student_id', $student->id)
            ->whereIn('exam_id', $exams->where('is_class_exam', true)->pluck('id'))
            ->get()
            ->keyBy('exam_id');

        $exams->each(function (Exam $exam) use ($flatAttempts, $sectionedAttempts) {
            $exam->total_questions = $exam->is_class_exam ? $exam->class_exam_questions_count : $exam->questions_count;

            if ($exam->is_class_exam) {
                $attempt = $sectionedAttempts->get($exam->id);
                $exam->my_status = $attempt === null ? 'not_started' : ($attempt->is_exam_completed ? 'completed' : 'in_progress');
                $exam->my_score = $attempt && $attempt->is_exam_completed ? (new ClassExamScoreService())->compute($attempt) : null;
            } else {
                $attempt = $flatAttempts->get($exam->id);
                $exam->my_status = $attempt === null ? 'not_started' : ($attempt->is_exam_completed ? 'completed' : 'in_progress');
                $exam->my_score = $attempt && $attempt->is_exam_completed ? (new ScoreService())->fetchExamScore($attempt) : null;
            }
        });

        return Response::apiSuccess('Class exams', StudentClassExamResource::collection($exams));
    }

    public function questions(string $slug, Exam $exam)
    {
        abort_if($exam->is_class_exam, 404, 'This exam uses sections — use the sections endpoint instead.');

        $student = Auth::guard('institute_student')->user();
        $class = $this->resolveEnrolledClass($slug);

        abort_unless(
            $class->exams()->where('exams.id', $exam->id)->where('exams.is_active', 1)->exists(),
            404,
            'This exam is not part of this class.'
        );

        if (!$exam->isWithinScheduledWindow()) {
            return Response::apiError($this->scheduleWindowMessage($exam), null, 403);
        }

        try {
            $this->startOrResume($exam, $student);
        } catch (ClientStudentExamException $e) {
            return Response::apiError($e->getMessage(), null, 409);
        }

        $questionsQuery = $exam->questions()
            ->with([
                'options:id,question_id,option',
                'options.media',
                'media',
                'student_answers' => fn ($q) => $q->whereHas(
                    'student_exam',
                    fn ($q) => $q->where('institute_student_id', $student->id)
                ),
            ]);

        if ($exam->is_shuffled_question) {
            $questionsQuery->inRandomOrder();
        }

        $questions = $questionsQuery->paginate(100);

        if ($exam->is_shuffled_option) {
            $questions->getCollection()->each(
                fn ($question) => $question->setRelation('options', $question->options->shuffle()->values())
            );
        }

        $totalChoosed = StudentExam::where('institute_student_id', $student->id)
            ->where('exam_id', $exam->id)
            ->first()
            ->answers()
            ->where('selected_option_id', '<>', null)
            ->count();

        $data = $this->setupPagination($questions, QuestionCollection::class, [
            'duration' => $exam->hisToMin(),
            'total_choosed_questions' => $totalChoosed,
        ])->data;

        return Response::apiSuccess('Questions retrieved successfully!', $data);
    }

    public function submitAnswers(Request $request, string $slug, Exam $exam)
    {
        abort_if($exam->is_class_exam, 404, 'This exam uses sections — use the section-answer endpoint instead.');

        $student = Auth::guard('institute_student')->user();
        $class = $this->resolveEnrolledClass($slug);

        abort_unless($class->exams()->where('exams.id', $exam->id)->exists(), 404, 'This exam is not part of this class.');

        $validatedData = $request->validate([
            'question_id' => 'nullable|array',
            'question_id.*' => 'integer|exists:questions,id',
            'option_id' => 'nullable|array',
            'option_id.*' => 'integer|exists:option_questions,id',
            'is_exam_completed' => 'required|between:0,1',
        ]);

        if ($request->question_id === null) {
            throw ValidationException::withMessages(['question_id' => 'Question id cannot be of type null']);
        } elseif ($request->option_id === null) {
            throw ValidationException::withMessages(['option_id' => 'Option id cannot be of type null']);
        }

        $received_questions = $validatedData['question_id'];
        $received_answers = $validatedData['option_id'];

        if (count($received_answers) != count($received_questions)) {
            return Response::apiError('No. of questions does not match with the No. of options.', null, 422);
        }

        $student_exam = StudentExam::where('institute_student_id', $student->id)->where('exam_id', $exam->id)->first();
        if ($student_exam == null) {
            return Response::apiError('This exam has not been initialized properly', null, 422);
        }

        $exam_question_option = Exam::select('id', 'exam_name')
            ->with(['questions' => fn ($qry) => $qry->select('id', 'exam_id')->with(['options' => fn ($qry) => $qry->select('id', 'question_id', 'option', 'value')])])
            ->firstWhere('id', $exam->id);

        $excepted_questions = $exam_question_option->questions->pluck('id')->all();
        $expected_options = $exam_question_option->questions->flatMap(fn ($item) => $item->options)->pluck('id')->all();

        if (!empty(array_diff($received_questions, $excepted_questions))) {
            throw ValidationException::withMessages(['question_id' => 'question id does not exists within this exam question id.']);
        } elseif (!empty(array_diff($received_answers, $expected_options))) {
            throw ValidationException::withMessages(['option_id' => 'option id does not exists within this exam questions option id.']);
        }

        $received_questions_answers = array_combine($received_questions, $received_answers);

        $temp = [];
        $answersheets = $student_exam->answers->pluck('id', 'question_id');
        foreach ($exam_question_option->questions as $question) {
            if (array_key_exists($question->id, $received_questions_answers)) {
                $selected_option = $received_questions_answers[$question->id];
                $is_correct = $question->options->firstWhere('id', $selected_option)->value == 1 ? true : false;
                $temp[] = [
                    'id' => $answersheets[$question->id],
                    'student_exam_id' => $student_exam->id,
                    'question_id' => $question->id,
                    'selected_option_id' => $selected_option,
                    'is_correct' => $is_correct,
                ];
            }
        }

        DB::transaction(function () use ($student_exam, $temp, $validatedData) {
            $student_exam->answers()->upsert($temp, ['student_exam_id', 'question_id'], ['selected_option_id', 'is_correct']);
            $student_exam->update(['is_exam_completed' => $validatedData['is_exam_completed']]);
        });

        $student_exam->refresh();
        $student_exam->load(['answers', 'exam.questions'])
            ->loadCount([
                'answers as correct_answer_count' => fn ($q) => $q->where('is_correct', 1),
                'answers as incorrect_answer_count' => fn ($q) => $q->where('is_correct', 0),
                'answers as missed_answer_count' => fn ($q) => $q->where('is_correct', null),
            ]);

        $scores = (new ScoreService())->fetchExamScore($student_exam);
        $scores['is_exam_completed'] = (bool) $validatedData['is_exam_completed'];

        return Response::apiSuccess('Exam completed successfully.', $scores, 200);
    }

    public function result(string $slug, Exam $exam)
    {
        abort_if($exam->is_class_exam, 404, 'This exam uses sections — use the section-result endpoint instead.');

        $student = Auth::guard('institute_student')->user();
        $this->resolveEnrolledClass($slug);

        $studentExam = StudentExam::where('institute_student_id', $student->id)
            ->where('exam_id', $exam->id)
            ->withCount([
                'answers as correct_answer_count' => fn ($q) => $q->where('is_correct', 1),
                'answers as incorrect_answer_count' => fn ($q) => $q->where('is_correct', 0),
                'answers as missed_answer_count' => fn ($q) => $q->where('is_correct', null),
            ])
            ->first();

        if ($studentExam == null) {
            return Response::apiError('You have not attempted this exam yet.', null, 404);
        }
        if (!$studentExam->is_exam_completed) {
            return Response::apiError('You must finish this exam before accessing the result.');
        }

        $questions = $exam->questions()->with('options')->paginate();
        $data = $this->setupPagination($questions, QuestionCollection::class)->data;

        $user_choosed = $studentExam->answers->pluck('selected_option_id', 'question_id');

        $items = ($data['data'])->toArray(request());
        foreach ($items as $key => $value) {
            $items[$key]['user_choosed'] = $user_choosed->has($items[$key]['id']) ? $user_choosed[$items[$key]['id']] : null;
        }
        $data['data'] = $items;

        return Response::apiSuccess('Exam result', [
            'score' => (new ScoreService())->fetchExamScore($studentExam),
            'questions' => $data,
        ]);
    }

    /**
     * Sectioned class-exam flow below (is_class_exam = true) — a separate,
     * self-contained path from the legacy flat MCQ flow above. Kept apart so
     * linked pre-existing exams (which still use Question/Answersheet) are
     * completely unaffected.
     */
    public function sections(string $slug, Exam $exam)
    {
        abort_unless($exam->is_class_exam, 404, 'This exam does not use sections.');

        $student = Auth::guard('institute_student')->user();
        $class = $this->resolveEnrolledClass($slug);

        abort_unless(
            $class->exams()->where('exams.id', $exam->id)->where('exams.is_active', 1)->exists(),
            404,
            'This exam is not part of this class.'
        );

        if (!$exam->isWithinScheduledWindow()) {
            return Response::apiError($this->scheduleWindowMessage($exam), null, 403);
        }

        try {
            $attempt = $this->startOrResumeSectioned($exam, $student);
        } catch (ClientStudentExamException $e) {
            return Response::apiError($e->getMessage(), null, 409);
        }

        $myAnswers = $attempt->classExamAnswers()->get()->keyBy('class_exam_question_id');

        $sections = $exam->classExamSections()->with('questions.options')->get()->map(function ($section) use ($myAnswers) {
            return [
                'id' => $section->id,
                'title' => $section->title,
                'detail' => $section->detail,
                'questions' => $section->questions->map(function (ClassExamQuestion $question) use ($myAnswers) {
                    $answer = $myAnswers->get($question->id);

                    return [
                        'id' => $question->id,
                        'question_type' => $question->question_type,
                        'question' => $question->question,
                        'full_marks' => (float) $question->full_marks,
                        'image_url' => $question->getFirstMediaUrl(ClassExamQuestion::QUESTION_IMAGE) ?: null,
                        'options' => $question->question_type === 'mcq'
                            ? $question->options->map(fn ($o) => [
                                'id' => $o->id,
                                'option' => $o->option,
                                'image_url' => $o->image_url,
                            ])
                            : null,
                        'my_answer' => [
                            'selected_option_id' => $answer?->class_exam_question_option_id,
                            'answer_text' => $answer?->answer_text,
                            'answer_file_url' => $answer?->answer_file_path ? Storage::disk('public')->url($answer->answer_file_path) : null,
                        ],
                    ];
                }),
            ];
        });

        return Response::apiSuccess('Exam sections', [
            'exam_name' => $exam->exam_name,
            'instructions' => $exam->instructions,
            'duration' => $exam->hisToMin(),
            'sections' => $sections,
        ]);
    }

    public function answerSectionQuestion(Request $request, string $slug, Exam $exam)
    {
        abort_unless($exam->is_class_exam, 404, 'This exam does not use sections.');

        $student = Auth::guard('institute_student')->user();
        $this->resolveEnrolledClass($slug);

        if (!$exam->isWithinScheduledWindow()) {
            return Response::apiError($this->scheduleWindowMessage($exam), null, 403);
        }

        $data = $request->validate([
            'question_id' => 'required|integer|exists:class_exam_questions,id',
            'selected_option_id' => 'nullable|integer|exists:class_exam_question_options,id',
            'answer_text' => 'nullable|string',
            'answer_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $question = ClassExamQuestion::findOrFail($data['question_id']);
        abort_unless($question->section->exam_id === $exam->id, 404, 'This question does not belong to this exam.');

        $studentExam = StudentExam::where('institute_student_id', $student->id)->where('exam_id', $exam->id)->first();
        if ($studentExam === null || $studentExam->is_exam_completed) {
            return Response::apiError('This exam is not currently in progress.', null, 409);
        }

        $answerData = ['student_exam_id' => $studentExam->id, 'class_exam_question_id' => $question->id];

        if ($question->question_type === 'mcq') {
            $option = $data['selected_option_id'] ? $question->options()->find($data['selected_option_id']) : null;
            abort_if($data['selected_option_id'] && !$option, 404, 'This option does not belong to this question.');

            $answerData['class_exam_question_option_id'] = $option?->id;
            $answerData['is_correct'] = $option ? (bool) $option->value : null;
            $answerData['marks_obtained'] = $option
                ? ($option->value ? (float) $question->full_marks : ($question->is_negative_marking ? -1 * (float) $question->negative_mark : 0))
                : null;
            $answerData['graded_at'] = $option ? now() : null;
        } else {
            $answerData['answer_text'] = $data['answer_text'] ?? null;
            if ($request->hasFile('answer_file')) {
                $existing = ClassExamAnswer::where('student_exam_id', $studentExam->id)
                    ->where('class_exam_question_id', $question->id)
                    ->first();
                if ($existing?->answer_file_path) {
                    Storage::disk('public')->delete($existing->answer_file_path);
                }
                $answerData['answer_file_path'] = $request->file('answer_file')->store('class-exam-answers', 'public');
            }
            // Subjective answers are graded manually by the teacher afterwards.
            $answerData['marks_obtained'] = null;
            $answerData['is_correct'] = null;
        }

        $answer = ClassExamAnswer::updateOrCreate(
            ['student_exam_id' => $studentExam->id, 'class_exam_question_id' => $question->id],
            $answerData
        );

        return Response::apiSuccess('Answer saved', [
            'question_id' => $question->id,
            'answer_file_url' => $answer->answer_file_path ? Storage::disk('public')->url($answer->answer_file_path) : null,
        ]);
    }

    public function completeSectionExam(string $slug, Exam $exam)
    {
        abort_unless($exam->is_class_exam, 404, 'This exam does not use sections.');

        $student = Auth::guard('institute_student')->user();
        $this->resolveEnrolledClass($slug);

        $studentExam = StudentExam::where('institute_student_id', $student->id)->where('exam_id', $exam->id)->first();
        if ($studentExam === null) {
            return Response::apiError('This exam has not been initialized properly', null, 422);
        }

        $studentExam->update(['is_exam_completed' => 1]);

        return Response::apiSuccess('Exam completed successfully.', (new ClassExamScoreService())->compute($studentExam));
    }

    public function sectionResult(string $slug, Exam $exam)
    {
        abort_unless($exam->is_class_exam, 404, 'This exam does not use sections.');

        $student = Auth::guard('institute_student')->user();
        $this->resolveEnrolledClass($slug);

        $studentExam = StudentExam::where('institute_student_id', $student->id)->where('exam_id', $exam->id)->first();
        if ($studentExam === null) {
            return Response::apiError('You have not attempted this exam yet.', null, 404);
        }
        if (!$studentExam->is_exam_completed) {
            return Response::apiError('You must finish this exam before accessing the result.');
        }

        $myAnswers = $studentExam->classExamAnswers()->with('selectedOption')->get()->keyBy('class_exam_question_id');

        $sections = $exam->classExamSections()->with('questions.options')->get()->map(function ($section) use ($myAnswers) {
            return [
                'id' => $section->id,
                'title' => $section->title,
                'questions' => $section->questions->map(function (ClassExamQuestion $question) use ($myAnswers) {
                    $answer = $myAnswers->get($question->id);

                    return [
                        'id' => $question->id,
                        'question_type' => $question->question_type,
                        'question' => $question->question,
                        'full_marks' => (float) $question->full_marks,
                        'options' => $question->question_type === 'mcq'
                            ? $question->options->map(fn ($o) => ['id' => $o->id, 'option' => $o->option, 'is_correct' => (bool) $o->value])
                            : null,
                        'my_answer' => [
                            'selected_option_id' => $answer?->class_exam_question_option_id,
                            'answer_text' => $answer?->answer_text,
                            'answer_file_url' => $answer?->answer_file_path ? Storage::disk('public')->url($answer->answer_file_path) : null,
                        ],
                        'is_correct' => $answer?->is_correct,
                        'marks_obtained' => $answer?->marks_obtained !== null ? (float) $answer->marks_obtained : null,
                        'is_graded' => $question->question_type === 'mcq' ? true : $answer?->graded_at !== null,
                    ];
                }),
            ];
        });

        return Response::apiSuccess('Exam result', array_merge((new ClassExamScoreService())->compute($studentExam), [
            'sections' => $sections,
        ]));
    }

    private function scheduleWindowMessage(Exam $exam): string
    {
        $start = \Carbon\Carbon::parse($exam->exam_date . ' ' . $exam->exam_time);
        $end = \Carbon\Carbon::parse($exam->exam_date . ' ' . $exam->end_time);

        if (now()->lt($start)) {
            return 'This exam has not started yet. It opens on ' . $start->format('M j, Y \a\t g:i A') . '.';
        }

        return 'This exam has ended. It was open until ' . $end->format('M j, Y \a\t g:i A') . '.';
    }

    private function startOrResume(Exam $exam, $student): StudentExam
    {
        DB::transaction(function () use ($exam, $student, &$attempt) {
            $attempt = StudentExam::firstOrCreate(
                ['exam_id' => $exam->id, 'institute_student_id' => $student->id],
                []
            );

            if ($attempt->wasRecentlyCreated) {
                $answersData = $exam->questions
                    ->pluck('id')
                    ->map(fn ($id) => ['question_id' => $id])
                    ->toArray();

                $attempt->answers()->createMany($answersData);
            }

            if ($attempt->exists && $attempt->is_exam_completed) {
                throw new ClientStudentExamException('This exam is no longer available — already completed.');
            }
        });

        return $attempt;
    }

    private function startOrResumeSectioned(Exam $exam, $student): StudentExam
    {
        $attempt = StudentExam::firstOrCreate(
            ['exam_id' => $exam->id, 'institute_student_id' => $student->id],
            []
        );

        if ($attempt->exists && $attempt->is_exam_completed) {
            throw new ClientStudentExamException('This exam is no longer available — already completed.');
        }

        return $attempt;
    }

    private function resolveEnrolledClass(string $slug): Classroom
    {
        $student = Auth::guard('institute_student')->user();

        $class = Classroom::where('slug', $slug)
            ->where('institute_id', $student->institute_id)
            ->firstOrFail();

        $pivot = $class->students()->where('institute_student_id', $student->id)->first();
        abort_unless($pivot?->pivot?->status === 'enrolled', 403, 'You must be enrolled in this class to view this.');

        return $class;
    }
}
