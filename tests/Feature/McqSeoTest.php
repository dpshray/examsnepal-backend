<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * phpunit.xml points at the developer's MySQL database, so this switches to
 * in-memory sqlite with just the columns mcq:score-seo and the MCQ endpoints read.
 */
class McqSeoTest extends TestCase
{
    private const EXPLANATION = 'Vancomycin is the drug of choice for serious MRSA infections; penicillins are inactivated by altered PBPs.';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'seo_testing',
            'database.connections.seo_testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('seo_testing');
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        Schema::create('questions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('exam_id')->nullable();
            $t->unsignedBigInteger('exam_type_id')->nullable();
            $t->longText('question')->nullable();
            $t->string('slug')->nullable();
            $t->unsignedInteger('view_count')->default(0);
            $t->mediumText('explanation')->nullable();
            $t->string('seo_noindex_reason', 32)->nullable();
            $t->unsignedBigInteger('canonical_question_id')->nullable();
        });
        Schema::create('option_questions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('question_id');
            $t->text('option');
            $t->boolean('value')->default(false);
        });
        Schema::create('question_comments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('question_id');
            $t->string('name');
            $t->text('comment');
            $t->timestamps();
        });
        // Read when the command rebuilds topic pages (App\Services\Seo\McqTopics).
        Schema::create('exams', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('exam_type_id')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
        });
        Schema::create('exam_types', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->nullable();
        });
        Schema::create('subjects', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('answersheets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('question_id');
            $t->unsignedBigInteger('selected_option_id')->nullable();
            $t->boolean('is_correct')->nullable();
        });
        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->string('collection_name');
        });
    }

    private function question(int $id, string $text, ?string $explanation, array $options = ['Vancomycin', 'Penicillin']): void
    {
        DB::table('questions')->insert(['id' => $id, 'question' => $text, 'slug' => "q-{$id}", 'explanation' => $explanation]);
        foreach ($options as $i => $option) {
            DB::table('option_questions')->insert(['question_id' => $id, 'option' => $option, 'value' => $i === 0]);
        }
    }

    public function test_scores_questions_and_points_duplicates_at_the_best_copy(): void
    {
        $this->question(1, 'Drug of choice for MRSA?', 'Ans: (a)');           // thin, duplicate
        $this->question(2, 'Drug of choice for MRSA?', self::EXPLANATION);    // same question, best copy
        $this->question(3, 'drug of choice for MRSA?', self::EXPLANATION, ['Penicillin', 'Vancomycin']); // duplicate
        $this->question(4, 'The question is given below', self::EXPLANATION);
        $this->question(5, 'The question is given below', self::EXPLANATION, ['A', 'B']);
        DB::table('media')->insert(['model_type' => Question::class, 'model_id' => 5, 'collection_name' => Question::QUESTION_IMAGE]);
        $this->question(6, 'Which nerve supplies the deltoid muscle?', self::EXPLANATION, ['Axillary', 'Radial']);

        $this->artisan('mcq:score-seo')->assertSuccessful();

        $rows = DB::table('questions')->orderBy('id')->get()->keyBy('id');
        $this->assertSame([2, null, 2, null, null, null], $rows->pluck('canonical_question_id')->map(fn ($v) => $v === null ? null : (int) $v)->values()->all());
        $this->assertSame(['thin_explanation', null, null, 'missing_question', null, null], $rows->pluck('seo_noindex_reason')->values()->all());

        // Idempotent: a second run has nothing to change.
        $this->artisan('mcq:score-seo')->expectsOutputToContain('0 reason change(s), 0 canonical change(s)')->assertSuccessful();

        $this->assertSame(['q-2', 'q-5', 'q-6'], $this->getJson('/api/sitemap/mcqs')->json('data.*.slug'));
        $this->assertSame(3, $this->getJson('/api/sitemap/mcq-meta')->json('data.total'));

        $this->getJson('/api/free/mcq/q-3')->assertJsonPath('data.seo', ['indexable' => true, 'canonical_slug' => 'q-2']);
        $this->getJson('/api/free/mcq/q-4')->assertJsonPath('data.seo', ['indexable' => false, 'canonical_slug' => null]);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->question(1, 'Drug of choice for MRSA?', 'c.');

        $this->artisan('mcq:score-seo', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull(DB::table('questions')->value('seo_noindex_reason'));
    }

    public function test_view_endpoint_counts_a_page_view(): void
    {
        $this->question(1, 'Drug of choice for MRSA?', self::EXPLANATION);

        $this->postJson('/api/free/mcq/q-1/view')->assertOk();
        $this->postJson('/api/free/mcq/missing/view')->assertNotFound();

        $this->assertSame(1, (int) DB::table('questions')->value('view_count'));
    }
}
