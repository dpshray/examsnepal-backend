<?php

namespace Tests\Feature;

use App\Services\Seo\McqTopics;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * phpunit.xml points at the developer's MySQL database, so this switches to
 * in-memory sqlite with just the columns the topic pages read.
 */
class McqTopicsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'topics_testing',
            'database.connections.topics_testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array',
        ]);
        DB::purge('topics_testing');
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        Schema::create('exam_types', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->nullable();
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
            $t->unsignedBigInteger('exam_type_id');
            $t->longText('question');
            $t->string('slug')->nullable();
            $t->mediumText('explanation')->nullable();
            $t->unsignedInteger('view_count')->default(0);
            $t->string('seo_noindex_reason', 32)->nullable();
            $t->unsignedBigInteger('canonical_question_id')->nullable();
        });
        Schema::create('option_questions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('question_id');
            $t->text('option');
            $t->boolean('value')->default(false);
        });
        Schema::create('answersheets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('student_exam_id');
            $t->unsignedBigInteger('question_id');
            $t->unsignedBigInteger('selected_option_id')->nullable();
            $t->boolean('is_correct')->nullable();
        });
        Schema::create('question_comments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('question_id');
            $t->string('name');
            $t->text('comment');
            $t->timestamps();
        });
        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->string('collection_name');
            $t->string('name')->nullable();
            $t->string('file_name')->nullable();
            $t->string('disk')->nullable();
            $t->json('manipulations')->nullable();
            $t->json('custom_properties')->nullable();
            $t->json('generated_conversions')->nullable();
            $t->json('responsive_images')->nullable();
            $t->unsignedInteger('order_column')->nullable();
            $t->string('uuid')->nullable();
            $t->string('mime_type')->nullable();
            $t->string('conversions_disk')->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
        });
        Schema::create('exam_guides', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('exam_category_id');
            $t->string('name');
            $t->string('slug');
            $t->string('status');
            $t->string('mock_test_url')->nullable();
        });
        Schema::create('exam_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug');
        });

        DB::table('exam_types')->insert(['id' => 1, 'name' => 'Medical Exams', 'slug' => 'medical-exams']);
        DB::table('subjects')->insert([['id' => 1, 'name' => 'Obstetrics & Gynaecology'], ['id' => 2, 'name' => 'Anatomy']]);
        DB::table('exams')->insert([
            ['id' => 1, 'exam_type_id' => 1, 'subject_id' => 1],
            ['id' => 2, 'exam_type_id' => 1, 'subject_id' => 2],
            ['id' => 3, 'exam_type_id' => 1, 'subject_id' => null],
        ]);
        DB::table('exam_categories')->insert(['id' => 1, 'name' => 'Medical', 'slug' => 'medical']);
        DB::table('exam_guides')->insert([
            ['exam_category_id' => 1, 'name' => 'NMCLE', 'slug' => 'nmcle', 'status' => 'published', 'mock_test_url' => '/find-mcq/medical-exams'],
            ['exam_category_id' => 1, 'name' => 'Draft guide', 'slug' => 'draft', 'status' => 'draft', 'mock_test_url' => '/find-mcq/medical-exams'],
        ]);
    }

    private function questions(int $examId, int $count, array $attrs = []): void
    {
        $id = (int) DB::table('questions')->max('id');
        foreach (range(1, $count) as $_) {
            $id++;
            DB::table('questions')->insert($attrs + ['id' => $id, 'exam_id' => $examId, 'exam_type_id' => 1, 'question' => "Question {$id}?", 'slug' => "q-{$id}", 'explanation' => 'Because.']);
            DB::table('option_questions')->insert([['question_id' => $id, 'option' => 'Right', 'value' => 1], ['question_id' => $id, 'option' => 'Wrong', 'value' => 0]]);
        }
    }

    public function test_topics_need_enough_indexable_questions_in_a_subject(): void
    {
        $this->questions(1, 25);                                          // OBG: 25 indexable
        $this->questions(1, 30, ['seo_noindex_reason' => 'thin_explanation']); // not counted
        $this->questions(2, 19);                                          // Anatomy: too few
        $this->questions(3, 40);                                          // no subject

        $topics = app(McqTopics::class)->all();

        $this->assertCount(1, $topics);
        $this->assertSame('obstetrics-gynaecology', $topics[0]['subject_slug']);
        $this->assertSame(25, $topics[0]['question_count']);
    }

    public function test_topic_page_paginates_answers_and_links_guides(): void
    {
        $this->questions(1, 25);

        $this->getJson('/api/free/mcq-topics/medical-exams/obstetrics-gynaecology?page=2')
            ->assertOk()
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.last_page', 2)
            ->assertJsonCount(5, 'data.questions')
            ->assertJsonPath('data.questions.0.options.0.is_correct', true)
            ->assertJsonPath('data.guides', [['name' => 'NMCLE', 'url' => '/exams/medical/nmcle']]);

        $this->getJson('/api/free/mcq-topics/medical-exams/obstetrics-gynaecology?page=3')->assertNotFound();
        $this->getJson('/api/free/mcq-topics/medical-exams/anatomy')->assertNotFound();
        $this->getJson('/api/free/mcq-topics')->assertOk()->assertJsonPath('data.0.category_slug', 'medical-exams');
    }

    public function test_accuracy_is_shown_only_with_enough_answered_questions(): void
    {
        $this->questions(1, 20);
        foreach (range(1, 600) as $i) {
            DB::table('answersheets')->insert(['student_exam_id' => $i, 'question_id' => 1 + $i % 20, 'selected_option_id' => 1, 'is_correct' => $i % 4 !== 0]);
        }
        DB::table('answersheets')->insert(['student_exam_id' => 999, 'question_id' => 1, 'selected_option_id' => null, 'is_correct' => 0]); // unanswered, ignored

        $topic = app(McqTopics::class)->all()[0];

        $this->assertSame(600, $topic['answers']);
        $this->assertSame(75.0, $topic['accuracy_pct']);
    }

    public function test_mcq_detail_includes_its_topic(): void
    {
        $this->questions(1, 20);

        $this->getJson('/api/free/mcq/q-1')
            ->assertOk()
            ->assertJsonPath('data.topic', [
                'category_slug' => 'medical-exams',
                'category_name' => 'Medical Exams',
                'subject_slug' => 'obstetrics-gynaecology',
                'subject_name' => 'Obstetrics & Gynaecology',
            ]);
    }
}
