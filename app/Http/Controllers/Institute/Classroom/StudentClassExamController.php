<?php

namespace App\Http\Controllers\Institute\Classroom;

use App\Exceptions\ClientStudentExamException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Institute\Classroom\StudentClassExamResource;
use App\Http\Resources\QuestionCollection;
use App\Models\Corporate\Classroom;
use App\Models\Exam;
use App\Models\StudentExam;
use App\Services\ScoreService;
use App\Traits\PaginatorTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

class StudentClassExamController extends Controller
{
    use PaginatorTrait;

    public function index(string $slug)
    {
        $student = Auth::guard('institute_student')->user();
        $class = $this->resolveEnrolledClass($slug);

        $exams = $class->exams()->withCount('questions')->orderByDesc('exams.id')->get();

        $attempts = StudentExam::where('institute_student_id', $student->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->withCount([
                'answers as correct_answer_count' => fn ($q) => $q->where('is_correct', 1),
                'answers as incorrect_answer_count' => fn ($q) => $q->where('is_correct', 0),
                'answers as missed_answer_count' => fn ($q) => $q->where('is_correct', null),
            ])
            ->get()
            ->keyBy('exam_id');

        $exams->each(function (Exam $exam) use ($attempts) {
            $exam->my_attempt = $attempts->get($exam->id);
        });

        return Response::apiSuccess('Class exams', StudentClassExamResource::collection($exams));
    }

    public function questions(string $slug, Exam $exam)
    {
        $student = Auth::guard('institute_student')->user();
        $class = $this->resolveEnrolledClass($slug);

        abort_unless($class->exams()->where('exams.id', $exam->id)->exists(), 404, 'This exam is not part of this class.');

        try {
            $this->startOrResume($exam, $student);
        } catch (ClientStudentExamException $e) {
            return Response::apiError($e->getMessage(), null, 409);
        }

        $questions = $exam->questions()
            ->with([
                'options:id,question_id,option',
                'options.media',
                'media',
                'student_answers' => fn ($q) => $q->whereHas(
                    'student_exam',
                    fn ($q) => $q->where('institute_student_id', $student->id)
                ),
            ])
            ->paginate(100);

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
