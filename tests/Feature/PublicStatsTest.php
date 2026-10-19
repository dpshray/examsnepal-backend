<?php

namespace Tests\Feature;

use App\Models\ExamGuide;
use App\Services\PublicStats;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * phpunit.xml points at the developer's MySQL database, so this switches to
 * in-memory sqlite with just the columns PublicStats reads.
 */
class PublicStatsTest extends TestCase
{
    private int $questionId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'stats_testing',
            'database.connections.stats_testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array',
        ]);
        DB::purge('stats_testing');
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        Schema::create('exam_types', function (Blueprint $t) {
            $t->id();
            $t->string('slug');
        });
        Schema::create('student_profiles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('exam_type_id')->nullable();
            $t->boolean('is_hidden')->default(false);
        });
        Schema::create('subjects', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('exams', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('exam_type_id');
            $t->unsignedBigInteger('subject_id')->nullable();
        });
        Schema::create('questions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('exam_id');
        });
        Schema::create('student_exams', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('exam_id');
            $t->boolean('is_exam_completed')->default(true);
            $t->decimal('score_pct', 5, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('answersheets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('student_exam_id');
            $t->unsignedBigInteger('question_id');
            $t->unsignedBigInteger('selected_option_id')->nullable();
            $t->boolean('is_correct')->nullable();
        });
        Schema::create('exam_guides', function (Blueprint $t) {
            $t->id();
            $t->string('status');
            $t->string('mock_test_url')->nullable();
        });

        DB::table('exam_types')->insert([['id' => 1, 'slug' => 'medical'], ['id' => 2, 'slug' => 'thin']]);
        DB::table('subjects')->insert([['id' => 1, 'name' => 'Anatomy'], ['id' => 2, 'name' => 'Surgery']]);
        DB::table('exams')->insert([
            ['id' => 1, 'exam_type_id' => 1, 'subject_id' => 1],
            ['id' => 2, 'exam_type_id' => 1, 'subject_id' => 2],
            ['id' => 3, 'exam_type_id' => 2, 'subject_id' => null],
        ]);
        DB::table('student_profiles')->insert([
            ['exam_type_id' => 1, 'is_hidden' => 0],
            ['exam_type_id' => 1, 'is_hidden' => 0],
            ['exam_type_id' => 1, 'is_hidden' => 1],
        ]);
        DB::table('exam_guides')->insert([['status' => 'published', 'mock_test_url' => '/find-mcq/medical'], ['status' => 'draft', 'mock_test_url' => null]]);
    }

    /** A completed test on $examId with $correct right answers out of $answered, plus $blank unanswered rows. */
    private function test(int $examId, float $scorePct, int $answered, int $correct, int $blank = 0): void
    {
        $id = DB::table('student_exams')->insertGetId(['exam_id' => $examId, 'score_pct' => $scorePct, 'created_at' => now()]);

        foreach (range(1, $answered + $blank) as $i) {
            $questionId = ++$this->questionId;
            DB::table('questions')->insert(['id' => $questionId, 'exam_id' => $examId]);
            DB::table('answersheets')->insert([
                'student_exam_id' => $id,
                'question_id' => $questionId,
                'selected_option_id' => $i <= $answered ? 1 : null,
                'is_correct' => $i <= $correct ? 1 : 0,
            ]);
        }
    }

    public function test_blank_submissions_are_excluded_from_scores_and_accuracy(): void
    {
        // 100 real tests scoring 1..100%, each 1 correct of 2 answered + 3 blank rows.
        foreach (range(1, 100) as $score) {
            $this->test($score % 2 ? 1 : 2, $score, answered: 2, correct: 1, blank: 3);
        }
        // 50 tests submitted with nothing selected - "completed" at 0%.
        foreach (range(1, 50) as $_) {
            $this->test(1, 0, answered: 0, correct: 0, blank: 5);
        }

        $stats = app(PublicStats::class)->forExamType(1);

        $this->assertSame(100, $stats['tests_completed']);
        $this->assertSame(50.5, $stats['average_score_pct']);
        $this->assertSame(50.5, $stats['median_score_pct']);
        $this->assertSame(90.1, $stats['top_10_percent_score_pct']);
        $this->assertSame(50.0, $stats['answer_accuracy_pct']); // blank rows (is_correct 0) ignored
        $this->assertSame(2, $stats['students_preparing']); // hidden profile excluded
    }

    public function test_exam_types_with_too_little_data_return_null(): void
    {
        foreach (range(1, PublicStats::MIN_TESTS_FOR_EXAM_STATS - 1) as $_) {
            $this->test(3, 60, answered: 1, correct: 1);
        }

        $this->assertNull(app(PublicStats::class)->forExamType(2));
    }

    public function test_subjects_need_enough_answers_to_be_shown(): void
    {
        // Anatomy (exam 1): 100 tests x 10 answers = 1000, 30% correct. Surgery (exam 2): 100 x 9 = 900.
        foreach (range(1, 100) as $_) {
            $this->test(1, 30, answered: 10, correct: 3);
            $this->test(2, 90, answered: 9, correct: 8);
        }

        $subjects = app(PublicStats::class)->forExamType(1)['hardest_subjects'];

        $this->assertSame([['name' => 'Anatomy', 'accuracy_pct' => 30.0, 'answers' => 1000]], $subjects);
    }

    public function test_guide_maps_to_exam_type_through_its_mock_test_url(): void
    {
        foreach (range(1, 100) as $_) {
            $this->test(1, 40, answered: 1, correct: 1);
        }
        $stats = app(PublicStats::class);

        $this->assertSame(100, $stats->forGuide(ExamGuide::first())['tests_completed']);
        $this->assertNull($stats->forGuide(ExamGuide::where('status', 'draft')->first()));
    }

    public function test_site_endpoint_counts_only_real_activity(): void
    {
        $this->test(1, 50, answered: 2, correct: 1, blank: 1);
        $this->test(1, 0, answered: 0, correct: 0, blank: 4);

        $this->getJson('/api/free/stats')
            ->assertOk()
            ->assertJsonPath('data.students', 2)
            ->assertJsonPath('data.questions', 7)
            ->assertJsonPath('data.tests_completed', 1)
            ->assertJsonPath('data.answers_submitted', 2)
            ->assertJsonPath('data.tests_last_30_days', 1)
            ->assertJsonPath('data.exam_guides', 1);
    }
}
