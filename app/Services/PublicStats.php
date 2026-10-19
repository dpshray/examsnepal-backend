<?php

namespace App\Services;

use App\Models\ExamGuide;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate, anonymous platform numbers shown publicly on the website
 * (homepage "in numbers" strip, per-exam performance on /exams guides).
 *
 * Every figure is computed from real rows - nothing is seeded or rounded up
 * here (the frontend rounds *down* for display). Per-exam blocks are withheld
 * until there's enough data to be meaningful, so a thin exam never shows
 * "3 students, 12% average".
 */
class PublicStats
{
    public const CACHE_TTL_SECONDS = 6 * 3600;

    /** Minimum completed tests before an exam's performance stats are shown. */
    public const MIN_TESTS_FOR_EXAM_STATS = 100;

    /** Minimum tagged answers before a subject's accuracy is shown. */
    public const MIN_ANSWERS_PER_SUBJECT = 1000;

    public function site(bool $fresh = false): array
    {
        $key = 'public_stats:site';
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::CACHE_TTL_SECONDS, fn () => [
            'students' => DB::table('student_profiles')->where('is_hidden', 0)->count(),
            'questions' => DB::table('questions')->count(),
            'tests_completed' => $this->answeredTests()->count(),
            'answers_submitted' => DB::table('answersheets')->whereNotNull('selected_option_id')->count(),
            // created_at is only reliably populated on recent rows, which is
            // exactly the window this counts.
            'tests_last_30_days' => $this->answeredTests()
                ->where('student_exams.created_at', '>=', now()->subDays(30))
                ->count(),
            'exam_guides' => ExamGuide::where('status', 'published')->count(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Performance stats for the exam type a guide's mock test links to
     * (mock_test_url is "/find-mcq/{exam_type_slug}"), or null when the guide
     * isn't linked to an exam type or there's too little data.
     */
    public function forGuide(ExamGuide $guide, bool $fresh = false): ?array
    {
        if (! preg_match('#^/find-mcq/([^/?\#]+)#', (string) $guide->mock_test_url, $m)) {
            return null;
        }

        $examTypeId = DB::table('exam_types')->where('slug', $m[1])->value('id');

        return $examTypeId ? $this->forExamType((int) $examTypeId, $fresh) : null;
    }

    public function forExamType(int $examTypeId, bool $fresh = false): ?array
    {
        $key = "public_stats:exam_type:{$examTypeId}";
        if ($fresh) {
            Cache::forget($key);
        }

        // Cache the "not enough data" result too (as false), so thin exam
        // types don't re-run the heavy queries on every request.
        $stats = Cache::remember($key, self::CACHE_TTL_SECONDS, fn () => $this->computeExamType($examTypeId) ?? false);

        return $stats ?: null;
    }

    private function computeExamType(int $examTypeId): ?array
    {
        $scores = $this->answeredTests()
            ->join('exams', 'exams.id', '=', 'student_exams.exam_id')
            ->where('exams.exam_type_id', $examTypeId)
            ->whereNotNull('student_exams.score_pct')
            ->orderBy('student_exams.score_pct')
            ->pluck('student_exams.score_pct')
            ->map(fn ($s) => max(0.0, min(100.0, (float) $s)))
            ->all();

        if (count($scores) < self::MIN_TESTS_FOR_EXAM_STATS) {
            return null;
        }

        $examIds = DB::table('exams')->where('exam_type_id', $examTypeId)->pluck('id');

        $accuracy = DB::table('answersheets')
            ->join('questions', 'questions.id', '=', 'answersheets.question_id')
            ->whereIn('questions.exam_id', $examIds)
            ->whereNotNull('answersheets.selected_option_id')
            ->avg('answersheets.is_correct');

        $subjects = DB::table('answersheets')
            ->join('questions', 'questions.id', '=', 'answersheets.question_id')
            ->join('exams', 'exams.id', '=', 'questions.exam_id')
            ->join('subjects', 'subjects.id', '=', 'exams.subject_id')
            ->where('exams.exam_type_id', $examTypeId)
            ->whereNotNull('answersheets.selected_option_id')
            ->groupBy('subjects.id', 'subjects.name')
            ->havingRaw('count(*) >= ?', [self::MIN_ANSWERS_PER_SUBJECT])
            ->selectRaw('subjects.name as name, avg(answersheets.is_correct) as accuracy, count(*) as answers')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'accuracy_pct' => round((float) $row->accuracy * 100, 1),
                'answers' => (int) $row->answers,
            ])
            ->sortBy('accuracy_pct')
            ->values();

        return [
            'students_preparing' => DB::table('student_profiles')
                ->where('exam_type_id', $examTypeId)
                ->where('is_hidden', 0)
                ->count(),
            'questions' => DB::table('questions')->whereIn('exam_id', $examIds)->count(),
            'tests_completed' => count($scores),
            'average_score_pct' => round(array_sum($scores) / count($scores), 1),
            'median_score_pct' => round($this->percentile($scores, 50), 1),
            'top_10_percent_score_pct' => round($this->percentile($scores, 90), 1),
            'answer_accuracy_pct' => $accuracy === null ? null : round((float) $accuracy * 100, 1),
            'hardest_subjects' => $subjects->take(3)->all(),
            'easiest_subjects' => $subjects->count() >= 6 ? $subjects->reverse()->take(3)->values()->all() : [],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Completed tests where the student selected at least one answer. Every
     * question gets an answersheets row when a test starts (selected_option_id
     * null until answered), so a test submitted blank is "completed" with a
     * 0% score - counting those would drag every average toward zero.
     */
    private function answeredTests()
    {
        return DB::table('student_exams')
            ->where('student_exams.is_exam_completed', 1)
            ->whereExists(fn ($q) => $q->from('answersheets')
                ->whereColumn('answersheets.student_exam_id', 'student_exams.id')
                ->whereNotNull('answersheets.selected_option_id'));
    }

    /** Linear-interpolated percentile of an ascending-sorted list. */
    private function percentile(array $sorted, float $p): float
    {
        $index = ($p / 100) * (count($sorted) - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($index - $lower);
    }
}
