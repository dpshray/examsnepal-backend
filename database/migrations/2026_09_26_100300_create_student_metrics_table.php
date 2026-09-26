<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One denormalised row per student, rebuilt hourly by marketing:refresh-metrics
 * and per-student on exam submission. The marketing dashboard and the
 * automation engine read only from here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_metrics', function (Blueprint $table) {
            $table->foreignId('student_id')->primary()->constrained('student_profiles')->cascadeOnDelete();
            $table->unsignedBigInteger('exam_type_id')->nullable()->index();
            $table->timestamp('signed_up_at')->nullable()->index();

            $table->unsignedInteger('total_attempts')->default(0);
            $table->unsignedInteger('free_attempts')->default(0);
            $table->unsignedInteger('sprint_attempts')->default(0);
            $table->unsignedInteger('mock_attempts')->default(0);
            $table->unsignedInteger('topic_attempts')->default(0);
            $table->unsignedInteger('attempts_last_7d')->default(0);
            $table->unsignedInteger('attempts_last_14d')->default(0);
            $table->unsignedInteger('attempts_last_30d')->default(0);
            $table->timestamp('first_attempt_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable()->index();
            $table->unsignedInteger('days_since_last_attempt')->nullable();

            $table->decimal('avg_score_pct', 5, 2)->nullable();
            $table->decimal('last_score_pct', 5, 2)->nullable();
            $table->decimal('best_score_pct', 5, 2)->nullable();
            $table->string('score_trend', 20)->default('insufficient_data');
            $table->unsignedBigInteger('weakest_subject_id')->nullable();
            $table->decimal('weakest_subject_score_pct', 5, 2)->nullable();
            $table->unsignedBigInteger('strongest_subject_id')->nullable();
            $table->unsignedInteger('current_streak_days')->default(0);
            $table->unsignedInteger('longest_streak_days')->default(0);
            $table->decimal('percentile_in_exam', 5, 2)->nullable();

            $table->string('subscription_status', 20)->default('never')->index();
            $table->date('subscription_ends_at')->nullable();
            $table->decimal('total_paid_npr', 10, 2)->default(0);
            $table->unsignedInteger('payments_count')->default(0);

            $table->unsignedInteger('pricing_page_views')->default(0); // last 30 days
            $table->timestamp('last_pricing_viewed_at')->nullable();
            $table->timestamp('checkout_started_at')->nullable();
            $table->timestamp('checkout_completed_at')->nullable();

            $table->unsignedTinyInteger('lead_score')->default(0)->index();
            $table->json('lead_score_breakdown')->nullable();
            $table->string('lifecycle_stage', 30)->nullable()->index();
            $table->json('segments')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_metrics');
    }
};
