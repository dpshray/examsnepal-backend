<?php

namespace Tests\Feature\Marketing;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * phpunit.xml points at the developer's MySQL database, so marketing tests
 * switch to in-memory sqlite. The legacy core tables are recreated here with
 * just the columns marketing reads (their original migrations don't run on
 * sqlite), then the real marketing migrations are applied on top.
 */
abstract class MarketingDatabaseTestCase extends TestCase
{
    private static int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'marketing_testing',
            'database.connections.marketing_testing' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'marketing.queue_connection' => 'sync',
        ]);
        DB::purge('marketing_testing');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run marketing tests on a non-sqlite DB');

        Schema::create('exam_types', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('student_profiles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('exam_type_id')->nullable();
            $t->string('name');
            $t->string('email');
            $t->string('phone')->nullable();
            $t->string('google_id')->nullable();
            $t->text('date')->nullable();
            $t->string('fcm_token')->nullable();
            $t->string('requested_from')->nullable();
            $t->timestamp('email_verified_at')->nullable();
        });
        Schema::create('exams', function (Blueprint $t) {
            $t->id();
            $t->string('exam_name')->nullable();
            $t->string('status')->nullable();
            $t->unsignedBigInteger('exam_type_id')->nullable();
            $t->string('exam_mode')->default('open');
            $t->string('live')->nullable();
            $t->string('exam_date')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('subject_source', 10)->nullable();
            $t->boolean('is_class_exam')->default(false);
            $t->decimal('points_per_question', 5, 2)->default(1);
            $t->boolean('is_negative_marking')->nullable();
            $t->decimal('negative_marking_point', 5, 2)->nullable();
        });
        Schema::create('questions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('exam_id')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
        });
        Schema::create('subjects', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->nullable();
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
        Schema::create('student_exams', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('exam_id');
            $t->unsignedBigInteger('student_id')->nullable();
            $t->boolean('is_exam_completed')->default(false);
            $t->timestamps();
        });
        Schema::create('answersheets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('student_exam_id');
            $t->unsignedBigInteger('question_id');
            $t->boolean('is_correct')->nullable();
        });
        Schema::create('subscription_types', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('exam_type_id');
            $t->unsignedTinyInteger('duration');
            $t->decimal('price', 8, 2);
        });
        DB::table('exam_types')->insert([['id' => 1, 'name' => 'Medical'], ['id' => 2, 'name' => 'Nursing'], ['id' => 9, 'name' => 'Dental']]);
        DB::table('subscription_types')->insert([
            ['id' => 1, 'exam_type_id' => 1, 'duration' => 1, 'price' => 1000],
            ['id' => 2, 'exam_type_id' => 1, 'duration' => 3, 'price' => 2500],
            ['id' => 3, 'exam_type_id' => 1, 'duration' => 6, 'price' => 4000],
            ['id' => 4, 'exam_type_id' => 2, 'duration' => 1, 'price' => 700],
        ]);
        Schema::create('subscribers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('student_profile_id');
            $t->unsignedBigInteger('subscription_type_id');
            $t->decimal('price', 8, 2);
            $t->decimal('paid', 8, 2);
            $t->date('start_date');
            $t->date('end_date');
            $t->string('transaction_id');
            $t->string('payment_status')->nullable();
            $t->dateTime('subscribed_at');
            $t->string('remark')->nullable();
            $t->longText('data')->nullable();
            $t->boolean('status')->default(true);
        });

        foreach ([
            '2026_09_26_100000_add_marketing_fields_to_student_profiles_table',
            '2026_09_26_100100_add_score_fields_to_student_exams_table',
            '2026_09_26_100200_create_events_table',
            '2026_09_26_100300_create_student_metrics_table',
            '2026_09_26_100400_add_dashboard_support_to_marketing_tables',
            '2026_09_26_100500_create_marketing_messaging_tables',
            '2026_09_26_100600_add_trigger_filters_to_automations',
            '2026_09_26_100700_create_broadcasts_table',
            '2026_09_26_100800_add_multichannel_to_automations',
            '2026_09_26_100900_add_onboarded_at_to_student_profiles',
        ] as $migration) {
            Artisan::call('migrate', ['--path' => "database/migrations/{$migration}.php", '--database' => 'marketing_testing', '--force' => true]);
        }
    }

    protected function student(array $attrs = []): int
    {
        $n = ++self::$seq;
        return DB::table('student_profiles')->insertGetId($attrs + [
            'name' => "Student {$n}",
            'email' => "s{$n}@example.com",
            'exam_type_id' => 1,
            'created_at' => now()->subDays(60),
            'email_verified_at' => now()->subDays(60),
        ]);
    }

    protected function exam(int $status, int $questions = 10, array $attrs = []): int
    {
        $id = DB::table('exams')->insertGetId($attrs + ['exam_name' => 'Exam', 'status' => (string) $status, 'exam_type_id' => 1]);
        DB::table('questions')->insert(array_fill(0, $questions, ['exam_id' => $id]));
        return $id;
    }

    /** A completed attempt with a cached score. */
    protected function attempt(int $studentId, int $status, ?float $score, $at, bool $completed = true, ?int $examId = null): int
    {
        return DB::table('student_exams')->insertGetId([
            'exam_id' => $examId ?? $this->exam($status),
            'student_id' => $studentId,
            'is_exam_completed' => $completed,
            'score_pct' => $score,
            'submitted_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    protected function payment(int $studentId, array $attrs = []): int
    {
        return DB::table('subscribers')->insertGetId($attrs + [
            'student_profile_id' => $studentId,
            'subscription_type_id' => 2,
            'price' => 2500,
            'paid' => 2500,
            'start_date' => today()->subDays(10),
            'end_date' => today()->addDays(80),
            'transaction_id' => 'TXN' . random_int(10000, 99999),
            'data' => json_encode(['transaction_id' => 'x', 'ref_id' => 'r', 'token' => 't']),
            'payment_status' => 'PAYMENT_SUCCESS',
            'subscribed_at' => now()->subDays(10),
            'status' => 1,
        ]);
    }

    /** Shape of a subscription added by an admin through the admin panel. */
    protected function manualPayment(int $studentId, array $attrs = []): int
    {
        return $this->payment($studentId, $attrs + ['data' => json_encode(json_encode(['remark' => 'paid offline']))]);
    }

    protected function metrics(int $studentId): object
    {
        return DB::table('student_metrics')->where('student_id', $studentId)->first();
    }
}
