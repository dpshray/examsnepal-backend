<?php

namespace App\Services\Seo;

use App\Models\ExamGuide;
use App\Models\Question;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public MCQ topic pages: one per exam category (exam type) + subject, e.g.
 * "Pharmacology MCQs for Medical NMCLE / Loksewa / MDMS". Single-question
 * pages rarely rank; a page of 20 questions with answers and explanations on
 * one topic can. Built only from indexable, non-duplicate questions
 * (Question::inSitemap) whose exam has a subject (exams.subject_id).
 */
class McqTopics
{
    /** Below this many indexable questions a topic page would be thin. */
    public const MIN_QUESTIONS = 20;

    public const PER_PAGE = 20;

    private const CACHE_KEY = 'mcq_topics:v1';

    private const CACHE_TTL_SECONDS = 24 * 3600;

    /**
     * Every topic with enough questions, biggest first:
     * [exam_type_id, category_slug, category_name, subject_id, subject_slug,
     *  subject_name, question_count, answers, accuracy_pct].
     */
    public function all(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => $this->compute());
    }

    public function find(string $categorySlug, string $subjectSlug): ?array
    {
        foreach ($this->all() as $topic) {
            if ($topic['category_slug'] === $categorySlug && $topic['subject_slug'] === $subjectSlug) {
                return $topic;
            }
        }

        return null;
    }

    public function forQuestion(Question $question): ?array
    {
        $subjectId = DB::table('exams')->where('id', $question->exam_id)->value('subject_id');

        foreach ($subjectId ? $this->all() : [] as $topic) {
            if ($topic['exam_type_id'] === (int) $question->exam_type_id && $topic['subject_id'] === (int) $subjectId) {
                return $topic;
            }
        }

        return null;
    }

    public function questions(array $topic, int $page): LengthAwarePaginator
    {
        return $this->topicQuery($topic['exam_type_id'], $topic['subject_id'])
            ->select('questions.*')
            ->with(['options', 'media'])
            ->orderBy('questions.id')
            ->paginate(self::PER_PAGE, ['questions.*'], 'page', $page);
    }

    /** Other topics in the same category, biggest first. */
    public function related(array $topic, int $limit = 12): array
    {
        return array_values(array_slice(array_filter(
            $this->all(),
            fn ($t) => $t['exam_type_id'] === $topic['exam_type_id'] && $t['subject_id'] !== $topic['subject_id'],
        ), 0, $limit));
    }

    /** Published exam guides whose mock tests use this category's question bank. */
    public function guides(array $topic): array
    {
        return ExamGuide::query()
            ->where('status', 'published')
            ->where('mock_test_url', '/find-mcq/'.$topic['category_slug'])
            ->with('category:id,slug')
            ->orderBy('name')
            ->get()
            ->map(fn ($guide) => ['name' => $guide->name, 'url' => "/exams/{$guide->category->slug}/{$guide->slug}"])
            ->all();
    }

    private function topicQuery(int $examTypeId, int $subjectId)
    {
        return Question::query()->inSitemap()
            ->join('exams', 'exams.id', '=', 'questions.exam_id')
            ->where('questions.exam_type_id', $examTypeId)
            ->where('exams.subject_id', $subjectId);
    }

    private function compute(): array
    {
        $groups = Question::query()->inSitemap()
            ->join('exams', 'exams.id', '=', 'questions.exam_id')
            ->join('subjects', 'subjects.id', '=', 'exams.subject_id')
            ->join('exam_types', 'exam_types.id', '=', 'questions.exam_type_id')
            ->whereNotNull('exam_types.slug')
            ->groupBy('questions.exam_type_id', 'exam_types.slug', 'exam_types.name', 'subjects.id', 'subjects.name')
            ->havingRaw('count(*) >= ?', [self::MIN_QUESTIONS])
            ->selectRaw('questions.exam_type_id as exam_type_id, exam_types.slug as category_slug, exam_types.name as category_name,
                subjects.id as subject_id, subjects.name as subject_name, count(*) as question_count')
            ->orderByDesc('question_count')
            ->get();

        // How students do on each topic: answered questions only (every
        // question gets an answersheets row when a test starts, NULL until answered).
        $accuracy = DB::table('answersheets')
            ->join('questions', 'questions.id', '=', 'answersheets.question_id')
            ->join('exams', 'exams.id', '=', 'questions.exam_id')
            ->whereNotNull('exams.subject_id')
            ->whereNotNull('answersheets.selected_option_id')
            ->groupBy('questions.exam_type_id', 'exams.subject_id')
            ->selectRaw('questions.exam_type_id as t, exams.subject_id as s, count(*) as answers, avg(answersheets.is_correct) as accuracy')
            ->get()
            ->keyBy(fn ($row) => $row->t.':'.$row->s);

        return $groups->map(function ($g) use ($accuracy) {
            $stats = $accuracy[$g->exam_type_id.':'.$g->subject_id] ?? null;
            // Too few answers to say anything meaningful about difficulty.
            $answers = $stats ? (int) $stats->answers : 0;

            return [
                'exam_type_id' => (int) $g->exam_type_id,
                'category_slug' => $g->category_slug,
                'category_name' => $g->category_name,
                'subject_id' => (int) $g->subject_id,
                'subject_slug' => Str::slug($g->subject_name),
                'subject_name' => $g->subject_name,
                'question_count' => (int) $g->question_count,
                'answers' => $answers,
                'accuracy_pct' => $answers >= 500 ? round((float) $stats->accuracy * 100, 1) : null,
            ];
        })->values()->all();
    }
}
