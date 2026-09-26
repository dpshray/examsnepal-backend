<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache the final score on the attempt row so marketing metrics never have to
 * aggregate the (very large) answersheets table. Filled on submission by
 * AnswerSheetController and for history by `php artisan marketing:backfill`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_exams', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score_pct', 5, 2)->nullable();
            $table->index(['student_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('student_exams', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'submitted_at']);
            $table->dropColumn(['submitted_at', 'score_pct']);
        });
    }
};
