<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing dashboard (Phase 3): funnel milestone timestamps on
 * student_metrics so the funnel reads one table, saved custom segments, and
 * manual admin tags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_metrics', function (Blueprint $table) {
            $table->timestamp('habit_reached_at')->nullable();  // 3rd attempt within a 14-day window
            $table->timestamp('first_value_at')->nullable();    // first Sprint or Mock
            $table->timestamp('first_intent_at')->nullable();   // first pricing view or checkout
            $table->timestamp('first_paid_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index(); // latest of last login/activity and last attempt
        });

        Schema::table('student_exams', function (Blueprint $table) {
            $table->index('submitted_at');
        });

        Schema::create('marketing_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->json('filters');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('student_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->string('tag', 50);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['student_id', 'tag']);
            $table->index('tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_tags');
        Schema::dropIfExists('marketing_segments');
        Schema::table('student_exams', fn (Blueprint $table) => $table->dropIndex(['submitted_at']));
        Schema::table('student_metrics', function (Blueprint $table) {
            $table->dropIndex(['last_seen_at']);
            $table->dropColumn(['habit_reached_at', 'first_value_at', 'first_intent_at', 'first_paid_at', 'last_seen_at']);
        });
    }
};
